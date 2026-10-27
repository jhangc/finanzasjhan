<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Support\Money;

/**
 * Meses restantes para terminar de pagar una deuda (informativo).
 */
class PayoffEstimator
{
    private const MAX_MONTHS = 600;

    /**
     * @return array{months: ?int, months_with_interest: ?int}
     */
    public function estimate(Commitment $commitment): array
    {
        $balance = Money::of($commitment->total_balance);
        $monthly = Money::of($commitment->monthly_amount);

        if (! $commitment->isDebt() || Money::cmp($balance, '0') <= 0) {
            return ['months' => $commitment->isDebt() ? 0 : null, 'months_with_interest' => null];
        }

        if (Money::cmp($monthly, '0') <= 0) {
            return ['months' => null, 'months_with_interest' => null];
        }

        return [
            'months' => $this->months($balance, $monthly),
            'months_with_interest' => $commitment->interest_rate !== null
                ? $this->monthsWithInterest($balance, $monthly, (string) $commitment->interest_rate)
                : null,
        ];
    }

    /** ceil(saldo / cuota) */
    public function months(string $balance, string $monthly): int
    {
        $whole = bcdiv($balance, $monthly, 0);

        return (int) (bccomp(bcmul($whole, $monthly, 2), $balance, 2) < 0 ? bcadd($whole, '1', 0) : $whole);
    }

    /**
     * Interés simple mensual (TEA / 12) como aproximación.
     * Null si la cuota no alcanza para cubrir el interés.
     */
    public function monthsWithInterest(string $balance, string $monthly, string $annualRatePercent): ?int
    {
        $monthlyRate = bcdiv($annualRatePercent, '1200', 10);
        $remaining = $balance;

        for ($month = 1; $month <= self::MAX_MONTHS; $month++) {
            $interest = bcmul($remaining, $monthlyRate, 10);

            if ($month === 1 && bccomp($interest, $monthly, 10) >= 0) {
                return null;
            }

            $remaining = bcsub(bcadd($remaining, $interest, 10), $monthly, 10);

            if (bccomp($remaining, '0', 10) <= 0) {
                return $month;
            }
        }

        return null;
    }
}
