<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Estado de cuenta de una tarjeta: lo facturado al corte, cuánto falta pagar de eso,
 * cuándo vence y cuánto son consumos nuevos que se cobran en el siguiente estado de cuenta.
 */
class CardStatement
{
    /** Último día de corte ya ocurrido (hoy incluido). */
    public static function lastCut(int $cutDay, ?CarbonInterface $today = null): CarbonImmutable
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $thisMonth = DueDates::inMonth($cutDay, $today);

        return $thisMonth->lessThanOrEqualTo($today) ? $thisMonth : DueDates::inMonth($cutDay, $today->subMonthNoOverflow());
    }

    /** Primer día de pago posterior al corte. */
    public static function dueAfter(CarbonImmutable $cut, ?int $payDay): CarbonImmutable
    {
        $payDay ??= $cut->day;
        $candidate = DueDates::inMonth($payDay, $cut);

        return $candidate->greaterThan($cut) ? $candidate : DueDates::inMonth($payDay, $cut->addMonthNoOverflow());
    }

    /**
     * @return array{statement: string, cut_date: CarbonImmutable, due_date: CarbonImmutable, days_until: int,
     *     paid_since: string, pending: string, minimum: string, minimum_pending: string, new_charges: string,
     *     outdated: bool, paid: bool}|null
     */
    public function for(Commitment $card, ?CarbonInterface $today = null): ?array
    {
        if (! $card->is_card || $card->statement_balance === null || $card->statement_date === null) {
            return null;
        }

        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $cut = CarbonImmutable::instance($card->statement_date)->startOfDay();
        $due = self::dueAfter($cut, $card->pay_day);

        // Pagos hechos después del corte de este estado de cuenta.
        $paidSince = Money::of($card->payments()->whereDate('paid_at', '>', $cut->toDateString())->sum('amount'));

        $statement = Money::of($card->statement_balance);
        $pending = Money::positive(Money::sub($statement, $paidSince));
        $minimum = Money::of($card->monthly_amount);
        $debt = Money::positive($card->total_balance);

        return [
            'statement' => $statement,
            'cut_date' => $cut,
            'due_date' => $due,
            'days_until' => DueDates::daysUntil($due, $today),
            'paid_since' => $paidSince,
            'pending' => $pending,
            'minimum' => $minimum,
            'minimum_pending' => Money::positive(Money::sub($minimum, $paidSince)),
            'new_charges' => Money::positive(Money::sub($debt, $pending)),
            // Ya pasó otro corte: el banco emitió un estado de cuenta nuevo.
            'outdated' => $card->cut_day !== null && self::lastCut($card->cut_day, $today)->greaterThan($cut),
            'paid' => Money::isZero($pending),
        ];
    }
}
