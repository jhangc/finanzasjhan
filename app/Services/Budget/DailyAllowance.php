<?php

namespace App\Services\Budget;

use App\Models\Period;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DailyAllowance
{
    /**
     * Días que faltan del mes incluyendo hoy (mínimo 1).
     * Un periodo futuro cuenta el mes completo; uno pasado, 1.
     */
    public function daysLeft(Period $period, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $start = CarbonImmutable::create($period->year, $period->month, 1, 0, 0, 0, $today->getTimezone());
        $end = $start->endOfMonth()->startOfDay();

        if ($today->lessThan($start)) {
            return $start->daysInMonth;
        }

        if ($today->greaterThan($end)) {
            return 1;
        }

        return max(1, (int) $today->diffInDays($end) + 1);
    }

    public function amount(string $remaining, int $daysLeft): string
    {
        return Money::div($remaining, (string) max(1, $daysLeft));
    }
}
