<?php

namespace App\Services\Budget;

use App\Models\Account;
use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;
use App\Services\Debts\CommitmentOverview;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lo que necesita la pantalla "Hoy", serializable para que Alpine la actualice sin recargar.
 */
class TodayState
{
    private const RECENT = 6;

    private const UPCOMING = 4;

    public function __construct(
        private CascadeCalculator $cascade,
        private CommitmentOverview $commitments,
    ) {}

    public function for(Period $period, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? now());
        $cascade = $this->cascade->for($period, $today);
        $overview = $this->commitments->for($period->user, $period, $today);
        $next = $overview['next'];

        $unpaid = $overview['items']
            ->filter(fn ($i) => ! $i['covered'] && $i['due_date'] !== null)
            ->sortBy('days_until');

        return [
            'month' => ucfirst(PeriodBuilder::monthOf($period)->locale('es')->translatedFormat('F Y')),
            'cascade' => $cascade->toArray(),
            'commitments' => [
                'total' => $overview['total'],
                'paid' => $overview['paid'],
                'pending' => $overview['pending'],
                'next' => $next ? [
                    'name' => $next['commitment']->name,
                    'amount' => $next['pending'],
                    'date' => $next['due_date']->toDateString(),
                    'days_until' => $next['days_until'],
                    'overdue' => $next['overdue'],
                ] : null,
                'alerts' => $overview['items']
                    ->filter(fn ($i) => $i['due_soon'] || $i['overdue'])
                    ->map(fn ($i) => [
                        'id' => $i['commitment']->id,
                        'name' => $i['commitment']->name,
                        'days_until' => $i['days_until'],
                        'overdue' => $i['overdue'],
                    ])->values()->all(),
                'upcoming' => $unpaid->take(self::UPCOMING)->map(fn ($i) => [
                    'id' => $i['commitment']->id,
                    'name' => $i['commitment']->name,
                    'amount' => $i['pending'],
                    'date' => $i['due_date']->format('d/m'),
                    'days_until' => $i['days_until'],
                    'overdue' => $i['overdue'],
                    'is_card' => $i['commitment']->is_card,
                ])->values()->all(),
            ],
            'recent' => $this->recent($period, $today),
            'sources' => $this->sources($period->user),
        ];
    }

    /** Cuentas y tarjetas para "Pagas con" del input rápido. */
    private function sources(User $user): array
    {
        $accounts = Account::ownedBy($user)->orderBy('sort_order')->get()->map(fn (Account $a) => [
            'value' => "account:{$a->id}",
            'name' => $a->name,
            'hint' => Money::format($a->balance, $a->currency),
            'type' => 'account',
        ]);

        $cards = Commitment::ownedBy($user)->where('is_card', true)->where('active', true)->get()->map(fn (Commitment $c) => [
            'value' => "card:{$c->id}",
            'name' => $c->name,
            'hint' => $c->credit_limit !== null ? 'Disp. '.Money::format(Money::positive(Money::sub($c->credit_limit, $c->total_balance))) : 'Tarjeta',
            'type' => 'card',
        ]);

        return [
            'options' => $accounts->concat($cards)->values()->all(),
            'default' => $user->default_account_id ? "account:{$user->default_account_id}" : ($accounts->first()['value'] ?? 'none'),
        ];
    }

    /** Últimos gastos del mes: de categorías y pagos de gastos fijos/deudas. */
    private function recent(Period $period, CarbonImmutable $today): array
    {
        $when = fn (CarbonInterface $date, bool $withTime) => match (true) {
            $date->isSameDay($today) => 'Hoy'.($withTime ? ' '.$date->format('H:i') : ''),
            $date->isSameDay($today->subDay()) => 'Ayer'.($withTime ? ' '.$date->format('H:i') : ''),
            default => $date->format($withTime ? 'd/m H:i' : 'd/m'),
        };

        $movements = Movement::whereIn('envelope_id', $period->envelopes()->select('id'))
            ->with(['envelope:id,name,color', 'paidWith:id,name', 'account:id,name'])
            ->latest('spent_at')->latest('id')
            ->limit(self::RECENT)
            ->get()
            ->map(fn (Movement $m) => [
                'id' => 'm'.$m->id,
                'sort' => $m->spent_at->timestamp,
                'amount' => $m->amount,
                'envelope' => $m->envelope->name,
                'color' => $m->envelope->color,
                'note' => $m->note,
                'card' => $m->paidWith?->name,
                'account' => $m->account?->name,
                'when' => $when($m->spent_at, true),
            ]);

        $payments = $period->commitmentPayments()
            ->with(['commitment:id,name', 'paidWith:id,name', 'account:id,name'])
            ->latest('paid_at')->latest('id')
            ->limit(self::RECENT)
            ->get()
            ->map(fn (CommitmentPayment $p) => [
                'id' => 'p'.$p->id,
                // Fecha del pago con la hora en que se registró, para ordenar junto a los gastos.
                'sort' => $p->paid_at->copy()->setTimeFrom($p->created_at)->timestamp,
                'amount' => $p->amount,
                'envelope' => 'Gasto fijo',
                'color' => '#64748b',
                'note' => $p->commitment->name,
                'card' => $p->paidWith?->name,
                'account' => $p->account?->name,
                'when' => $when($p->paid_at, false),
            ]);

        return $movements->concat($payments)
            ->sortByDesc('sort')
            ->take(self::RECENT)
            ->values()
            ->all();
    }
}
