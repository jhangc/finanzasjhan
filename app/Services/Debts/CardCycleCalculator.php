<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Ciclo de facturación de una tarjeta: corte, pago y monto para no generar intereses.
 */
class CardCycleCalculator
{
    public function for(Commitment $card, ?CarbonInterface $today = null): CardCycle
    {
        if (! $card->is_card || $card->cut_day === null) {
            throw new InvalidArgumentException('El compromiso no es una tarjeta con día de corte.');
        }

        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $cutThisMonth = DueDates::inMonth($card->cut_day, $today);

        // El ciclo actual termina en el próximo corte (incluido).
        $currentEnd = $today->lessThanOrEqualTo($cutThisMonth)
            ? $cutThisMonth
            : DueDates::inMonth($card->cut_day, $today->addMonthNoOverflow());

        $closedEnd = DueDates::inMonth($card->cut_day, $currentEnd->subMonthNoOverflow());
        $currentStart = $closedEnd->addDay();
        $closedStart = DueDates::inMonth($card->cut_day, $closedEnd->subMonthNoOverflow())->addDay();

        return new CardCycle(
            currentStart: $currentStart,
            currentEnd: $currentEnd,
            currentSpent: $this->spentBetween($card, $currentStart, $currentEnd),
            closedStart: $closedStart,
            closedEnd: $closedEnd,
            amountToAvoidInterest: $this->spentBetween($card, $closedStart, $closedEnd),
            paymentDueDate: $this->paymentDate($card, $closedEnd),
        );
    }

    private function spentBetween(Commitment $card, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return Money::of(
            $card->cardMovements()
                ->whereBetween('spent_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount')
        );
    }

    /** Primer día de pago posterior al corte del ciclo cerrado. */
    private function paymentDate(Commitment $card, CarbonImmutable $closedEnd): CarbonImmutable
    {
        $payDay = $card->pay_day ?? $card->cut_day;
        $candidate = DueDates::inMonth($payDay, $closedEnd);

        return $candidate->greaterThan($closedEnd)
            ? $candidate
            : DueDates::inMonth($payDay, $closedEnd->addMonthNoOverflow());
    }
}
