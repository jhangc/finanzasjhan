<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Models\Period;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Estado de los compromisos activos en un mes: pagado, pendiente y próximo vencimiento.
 */
class CommitmentOverview
{
    /** Días de anticipación para el aviso "vence en N días". */
    public const DUE_SOON_DAYS = 5;

    public function __construct(
        private PayoffEstimator $payoff,
        private DebtProgress $progress,
        private CardStatement $statements,
    ) {}

    /**
     * @return array{items: Collection<int, array>, total: string, paid: string, pending: string, next: ?array}
     */
    public function for(User $user, ?Period $period, ?CarbonInterface $today = null, bool $onlyActive = true): array
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();

        $commitments = Commitment::ownedBy($user)
            ->when($onlyActive, fn ($q) => $q->active())
            ->withSum(['payments as paid_this_period' => fn ($q) => $q->where('period_id', $period?->id ?? 0)], 'amount')
            ->orderBy('pay_day')->orderBy('name')
            ->get();

        $items = $commitments->map(fn (Commitment $c) => $this->item($c, $today));

        $active = $items->where('commitment.active', true);

        return [
            'items' => $items,
            'total' => Money::sum($active->pluck('monthly')),
            'paid' => Money::sum($active->pluck('paid')),
            'pending' => Money::sum($active->pluck('pending')),
            'next' => $active->filter(fn ($i) => $i['due_date'] !== null && ! $i['covered'])
                ->sortBy('days_until')->first(),
        ];
    }

    private function item(Commitment $commitment, CarbonImmutable $today): array
    {
        $monthly = Money::of($commitment->monthly_amount);
        $paid = Money::of($commitment->paid_this_period);
        // Sin monto mínimo registrado (tarjetas), cualquier pago del mes cuenta como pagado.
        $isPaid = Money::isZero($monthly) ? Money::cmp($paid, '0') > 0 : Money::cmp($paid, $monthly) >= 0;

        $progress = $this->progress->for($commitment, $today);
        $firstDue = $progress['first_due'] ?? null;

        // Cuenta desde que se registró y, en deudas a plazo, desde la primera cuota:
        // si este mes vencía antes de eso, no se cobra este mes.
        $startsLater = false;
        $dueDate = null;
        if ($commitment->pay_day !== null) {
            $thisMonth = DueDates::inMonth($commitment->pay_day, $today);
            $registeredOn = CarbonImmutable::instance($commitment->created_at ?? $today)->startOfDay();
            $startsLater = ! $isPaid && ($thisMonth->lessThan($registeredOn) || ($firstDue && $thisMonth->lessThan($firstDue)));

            $dueDate = $thisMonth;
            if ($isPaid || $startsLater) {
                $next = DueDates::inMonth($commitment->pay_day, $today->addMonthNoOverflow());
                $dueDate = $firstDue && $firstDue->greaterThan($next) ? $firstDue : $next;
            }
        }

        $pending = $startsLater ? '0.00' : Money::positive(Money::sub($monthly, $paid));
        $percent = $startsLater ? 100 : min(100, Money::percent($paid, $monthly));

        // Tarjeta con estado de cuenta vigente: lo que hay que pagar es lo facturado al corte.
        $statement = $this->statements->for($commitment, $today);
        if ($statement && ! $statement['outdated']) {
            $monthly = $statement['statement'];
            $paid = $statement['paid_since'];
            $pending = $statement['pending'];
            $percent = min(100, Money::percent($paid, $monthly));
            $isPaid = $statement['paid'];
            $startsLater = false;
            $dueDate = $isPaid
                ? CardStatement::dueAfter(CardStatement::lastCut($commitment->cut_day, $today)->addMonthNoOverflow(), $commitment->pay_day)
                : $statement['due_date'];
        }

        $covered = $isPaid || $startsLater;
        $daysUntil = $dueDate ? DueDates::daysUntil($dueDate, $today) : null;
        $estimate = $this->payoff->estimate($commitment);
        $behind = false;

        // Préstamo quincenal o semanal: el vencimiento sale del calendario de cuotas.
        if ($progress && $progress['frequency'] !== 'monthly' && ! $progress['finished']) {
            $dueDate = $progress['next_due'];
            $daysUntil = $dueDate ? DueDates::daysUntil($dueDate, $today) : null;
            $behind = $progress['status'] === 'behind';
            $covered = ! $behind;
            $startsLater = false;
        }

        return [
            'commitment' => $commitment,
            'monthly' => $monthly,
            'paid' => $paid,
            'pending' => $pending,
            'percent' => $percent,
            'statement' => $statement,
            'is_paid' => $isPaid,
            'starts_later' => $startsLater,
            'covered' => $covered,
            'due_date' => $dueDate,
            'days_until' => $daysUntil,
            'overdue' => $behind || (! $covered && $daysUntil !== null && $daysUntil < 0),
            'due_soon' => ! $covered && $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= self::DUE_SOON_DAYS,
            'months_left' => $estimate['months'],
            'months_left_with_interest' => $estimate['months_with_interest'],
            'progress' => $progress,
        ];
    }
}
