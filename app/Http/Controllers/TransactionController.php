<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterTransactionRequest;
use App\Models\Movement;
use App\Services\Accounts\AccountsOverview;
use App\Services\Budget\CascadeCalculator;
use App\Services\Budget\EnvelopeStatus;
use App\Services\Budget\PeriodBuilder;
use App\Services\Debts\CommitmentOverview;
use App\Services\Movements\TransactionRecorder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla "Anotar": qué pagas y con qué pagas, o qué ingreso recibes.
 */
class TransactionController extends Controller
{
    public function create(Request $request, PeriodBuilder $periods, CascadeCalculator $cascade, CommitmentOverview $commitments, AccountsOverview $accounts): View
    {
        $this->authorize('create', Movement::class);

        $user = $request->user();
        $period = $periods->current($user);

        $categories = $period
            ? collect($cascade->for($period)->envelopes)->map(fn (EnvelopeStatus $e) => [
                'value' => "envelope:{$e->id}",
                'name' => $e->name,
                'color' => $e->color,
                'hint' => 'Te quedan '.Money::format($e->remaining),
                'over' => $e->state === EnvelopeStatus::STATE_OVER,
                'suggested' => null,
            ])->values()
            : collect();

        $items = $commitments->for($user, $period)['items'];
        $toOption = fn (array $i, string $hint) => [
            'value' => "commitment:{$i['commitment']->id}",
            'name' => $i['commitment']->name,
            'color' => null,
            'hint' => $hint,
            'over' => $i['overdue'],
            'suggested' => Money::isZero($i['pending']) ? null : $i['pending'],
            'is_card' => $i['commitment']->is_card,
        ];

        $fixed = $items->filter(fn ($i) => ! $i['commitment']->isDebt())
            ->map(fn ($i) => $toOption($i, match (true) {
                $i['starts_later'] => 'Primer pago el '.$i['due_date']?->format('d/m'),
                $i['covered'] => 'Pagado este mes',
                default => 'Falta '.Money::format($i['pending']),
            }))->values();
        $loans = $items->filter(fn ($i) => $i['commitment']->isDebt() && ! $i['commitment']->is_card)
            ->map(fn ($i) => $toOption($i, 'Debes '.Money::format($i['commitment']->total_balance)))->values();
        $cardsToPay = $items->filter(fn ($i) => $i['commitment']->is_card)
            ->map(fn ($i) => $toOption($i, $i['statement'] && ! $i['statement']['outdated']
                ? 'A pagar '.Money::format($i['statement']['pending'])
                : 'Debes '.Money::format($i['commitment']->total_balance)))->values();

        $money = $accounts->for($user);

        return view('transactions.create', [
            'period' => $period,
            'incomeSources' => $user->incomeSourceList(),
            'targets' => [
                ['key' => 'categories', 'title' => 'Gastos del día a día', 'options' => $categories],
                ['key' => 'fixed', 'title' => 'Gastos fijos', 'options' => $fixed],
                ['key' => 'loans', 'title' => 'Cuotas de préstamos', 'options' => $loans],
                ['key' => 'cards', 'title' => 'Pagar tarjeta', 'options' => $cardsToPay],
            ],
            'accounts' => $money['accounts']->map(fn ($a) => [
                'value' => "account:{$a->id}",
                'id' => $a->id,
                'name' => $a->name,
                'hint' => trim(($a->bank ? $a->bank.' · ' : '').'Saldo '.Money::format($a->balance, $a->currency)),
                'balance' => Money::of($a->balance),
            ])->values(),
            'cards' => $money['cards']->filter(fn ($c) => $c['commitment']->active)->map(fn ($c) => [
                'value' => "card:{$c['commitment']->id}",
                'name' => $c['commitment']->name,
                'hint' => $c['available'] !== null ? 'Disponible '.Money::format($c['available']) : 'Tarjeta de crédito',
            ])->values(),
        ]);
    }

    public function store(RegisterTransactionRequest $request, TransactionRecorder $recorder): RedirectResponse
    {
        $this->authorize('create', Movement::class);

        $message = $recorder->record($request->user(), $request);

        // Lo que escribió a mano queda como atajo para la próxima vez.
        if ($request->input('kind') === 'income') {
            $request->user()->addIncomeSource(trim((string) $request->input('income_source')));
        }

        return redirect()->route('transactions.create', ['kind' => $request->input('kind')])->with('status', '✓ '.$message);
    }
}
