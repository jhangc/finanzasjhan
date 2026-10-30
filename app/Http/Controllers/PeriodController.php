<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePeriodSetupRequest;
use App\Models\Commitment;
use App\Models\Envelope;
use App\Models\Period;
use App\Services\Budget\CascadeCalculator;
use App\Services\Budget\PeriodBuilder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeriodController extends Controller
{
    /** Armar el mes actual. */
    public function setup(Request $request, PeriodBuilder $periods, CascadeCalculator $cascade): View
    {
        $user = $request->user();
        $month = now()->startOfMonth();
        $period = $periods->current($user);

        if ($period) {
            $this->authorize('view', $period);
        }

        $form = $period
            ? [
                'expected_income' => Money::of($period->expected_income),
                'savings_target' => Money::of($period->savings_target),
                'envelopes' => $period->envelopes->map(fn (Envelope $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'allocated' => Money::of($e->allocated),
                    'color' => $e->color,
                ])->values()->all(),
            ]
            : $periods->draft($user, $month);

        $commitments = Commitment::ownedBy($user)->active()->orderBy('name')->get();
        $result = $period ? $cascade->for($period) : null;

        return view('periods.setup', [
            'period' => $period,
            'month' => $month,
            'form' => $form,
            'commitments' => $commitments,
            'commitmentsTotal' => Money::sum($commitments->pluck('monthly_amount')),
            'confirmedIncome' => $result?->incomeIsConfirmed ? $result->income : null,
            'incomes' => $period?->incomes()->earned()->orderBy('received_at')->get() ?? collect(),
            'previous' => $periods->previous($user, $month->year, $month->month),
            'usdRate' => $user->usd_rate,
        ]);
    }

    public function save(SavePeriodSetupRequest $request, PeriodBuilder $periods): RedirectResponse
    {
        $period = $periods->current($request->user());
        $period ? $this->authorize('update', $period) : $this->authorize('create', Period::class);

        $periods->saveSetup($request->user(), now()->startOfMonth(), $request->validated());

        return redirect()->route('dashboard')->with('status', 'Mes guardado.');
    }

    /** Botón "copiar sobres del mes anterior". */
    public function copyPrevious(Request $request, PeriodBuilder $periods): RedirectResponse
    {
        $user = $request->user();
        $month = now();

        $previous = $periods->previous($user, $month->year, $month->month);
        if ($previous === null) {
            return back()->withErrors(['envelopes' => 'No hay un mes anterior para copiar.']);
        }

        $this->authorize('view', $previous);

        $period = $periods->current($user) ?? $user->periods()->create([
            'year' => $month->year,
            'month' => $month->month,
            'expected_income' => $previous->expected_income,
            'savings_target' => $previous->savings_target,
        ]);
        $this->authorize('update', $period);

        $copied = $periods->copyEnvelopes($previous, $period);

        return redirect()->route('periods.setup')->with('status', $copied > 0
            ? ($copied === 1 ? 'Se copió 1 categoría' : "Se copiaron {$copied} categorías").' del mes anterior.'
            : 'Ya tenías todas las categorías del mes anterior.');
    }
}
