<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommitmentRequest;
use App\Http\Requests\UpdateCommitmentRequest;
use App\Models\Commitment;
use App\Services\Budget\PeriodBuilder;
use App\Services\Debts\CardCycleCalculator;
use App\Services\Debts\CardStatement;
use App\Services\Debts\CommitmentOverview;
use App\Services\Debts\CommitmentRegistrar;
use App\Services\Debts\DebtProgress;
use App\Services\Debts\PayoffEstimator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommitmentController extends Controller
{
    public function index(Request $request, PeriodBuilder $periods, CommitmentOverview $overview): View
    {
        $this->authorize('viewAny', Commitment::class);

        $user = $request->user();

        return view('commitments.index', [
            'overview' => $overview->for($user, $periods->current($user), onlyActive: false),
        ]);
    }

    public function show(Commitment $commitment, CardCycleCalculator $cycles, PayoffEstimator $payoff, DebtProgress $progress, CardStatement $statements): View
    {
        $this->authorize('view', $commitment);

        return view('commitments.show', [
            'commitment' => $commitment,
            'payments' => $commitment->payments()->with('vouchers')->orderByDesc('paid_at')->orderByDesc('id')->get(),
            'cycle' => $commitment->is_card && $commitment->cut_day ? $cycles->for($commitment) : null,
            'estimate' => $payoff->estimate($commitment),
            'progress' => $progress->for($commitment),
            'statement' => $statements->for($commitment),
        ]);
    }

    public function store(StoreCommitmentRequest $request, CommitmentRegistrar $registrar): RedirectResponse
    {
        $this->authorize('create', Commitment::class);

        $commitment = $registrar->create($request->user(), $request->validated(), $request->balanceWasTyped);

        // Vuelve a la lista desde donde se creó ("Planificar" o "Gastos fijos").
        return redirect()->route($this->returnRoute($request))
            ->with('status', "Gasto fijo «{$commitment->name}» agregado.");
    }

    public function update(UpdateCommitmentRequest $request, Commitment $commitment): RedirectResponse
    {
        $this->authorize('update', $commitment);

        $commitment->update($request->validated());

        return back()->with('status', 'Gasto fijo actualizado.');
    }

    public function destroy(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorize('delete', $commitment);

        $commitment->delete();

        return redirect()->route($this->returnRoute($request))
            ->with('status', 'Gasto fijo eliminado.');
    }

    /** Vuelve a la pantalla desde donde se hizo el cambio. */
    private function returnRoute(Request $request): string
    {
        return match ($request->input('from')) {
            'setup' => 'periods.setup',
            'accounts' => 'accounts.index',
            default => 'commitments.index',
        };
    }
}
