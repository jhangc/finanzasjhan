<?php

namespace App\Services\Debts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fechas de vencimiento a partir de un "día del mes" (se ajusta en meses cortos).
 */
class DueDates
{
    /** El día $day del mes de $reference, recortado al último día si no existe. */
    public static function inMonth(int $day, CarbonInterface $reference): CarbonImmutable
    {
        $month = CarbonImmutable::instance($reference)->startOfMonth()->startOfDay();

        return $month->setDay(min($day, $month->daysInMonth));
    }

    /** Próxima fecha con ese día, contando hoy. */
    public static function next(int $day, ?CarbonInterface $today = null): CarbonImmutable
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $candidate = self::inMonth($day, $today);

        return $candidate->lessThan($today) ? self::inMonth($day, $today->addMonthNoOverflow()) : $candidate;
    }

    /** Días desde hoy hasta $date (negativo si ya pasó). */
    public static function daysUntil(CarbonInterface $date, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();

        return (int) $today->diffInDays(CarbonImmutable::instance($date)->startOfDay(), false);
    }
}
