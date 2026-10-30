<?php

namespace App\Services\Budget;

use App\Models\Commitment;
use App\Models\Period;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * La cascada: ingresos → ahorro → compromisos → sobres → disponible hoy.
 */
class CascadeCalculator
{
    public function __construct(private DailyAllowance $daily) {}

    public function for(Period $period, ?CarbonInterface $today = null): CascadeResult
    {
        $income = $this->income($period);
        $savings = Money::of($period->savings_target);

        $commitments = Money::of(
            Commitment::ownedBy($period->user)->active()->sum('monthly_amount')
        );
        $paidThisMonth = Money::of($period->commitmentPayments()->sum('amount'));
        $pendingDue = Money::positive(Money::sub($commitments, $paidThisMonth));

        $availableForEnvelopes = Money::sub(Money::sub($income['amount'], $savings), $commitments);

        $envelopes = $period->envelopes()->withSum('movements', 'amount')->get()
            ->map(fn ($envelope) => EnvelopeStatus::make(
                $envelope->id,
                $envelope->name,
                $envelope->color,
                Money::of($envelope->allocated),
                Money::of($envelope->movements_sum_amount),
            ))
            ->values()
            ->all();

        $allocated = Money::sum(array_map(fn (EnvelopeStatus $e) => $e->allocated, $envelopes));
        $spent = Money::sum(array_map(fn (EnvelopeStatus $e) => $e->spent, $envelopes));
        $remaining = Money::sub($allocated, $spent);
        $overAllocated = Money::positive(Money::sub($allocated, $availableForEnvelopes));

        $daysLeft = $this->daily->daysLeft($period, $today);
        $requiredIncome = Money::add($savings, $commitments, $allocated);

        return new CascadeResult(
            income: $income['amount'],
            incomeIsConfirmed: $income['confirmed'],
            savings: $savings,
            commitments: $commitments,
            paidThisMonth: $paidThisMonth,
            pendingDue: $pendingDue,
            availableForEnvelopes: $availableForEnvelopes,
            allocated: $allocated,
            overAllocated: $overAllocated,
            spent: $spent,
            remaining: $remaining,
            daysLeft: $daysLeft,
            todayAllowance: $this->daily->amount($remaining, $daysLeft),
            requiredIncome: $requiredIncome,
            incomeGap: Money::positive(Money::sub($requiredIncome, $income['amount'])),
            envelopes: $envelopes,
        );
    }

    /**
     * Ingresos confirmados si existen; si no, el ingreso esperado del periodo.
     * Los ingresos en USD se convierten con el tipo de cambio manual del usuario.
     *
     * @return array{amount: string, confirmed: bool}
     */
    private function income(Period $period): array
    {
        $confirmed = $period->incomes()->earned()->where('confirmed', true)->get(['amount', 'currency']);

        if ($confirmed->isEmpty()) {
            return ['amount' => Money::of($period->expected_income), 'confirmed' => false];
        }

        $rate = $period->user->usd_rate;

        $total = Money::sum($confirmed->map(fn ($income) => $income->currency === 'USD' && $rate !== null
            ? Money::mul($income->amount, $rate)
            : $income->amount));

        return ['amount' => $total, 'confirmed' => true];
    }
}
