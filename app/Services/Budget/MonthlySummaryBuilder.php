<?php

namespace App\Services\Budget;

use App\Models\Commitment;
use App\Models\Movement;
use App\Models\Period;
use App\Support\Money;

/**
 * Resumen de un mes cerrado: ingresos, gastado, sobres excedidos, deuda y ahorro.
 */
class MonthlySummaryBuilder
{
    public function __construct(private CascadeCalculator $cascade) {}

    public function for(Period $period): array
    {
        $result = $this->cascade->for($period, PeriodBuilder::monthOf($period)->endOfMonth());

        $debtIds = Commitment::ownedBy($period->user)->where('type', Commitment::TYPE_DEBT)->pluck('id');
        $debtPaid = Money::of($period->commitmentPayments()->whereIn('commitment_id', $debtIds)->sum('amount'));
        $cardCharges = Money::of(
            Movement::whereIn('envelope_id', $period->envelopes()->select('id'))
                ->whereIn('paid_with_commitment_id', $debtIds)->sum('amount')
        );

        // Lo que quedó: ingreso - gastado en sobres - pagos de compromisos.
        $saved = Money::sub(Money::sub($result->income, $result->spent), $result->paidThisMonth);

        return [
            'period' => $period,
            'income' => $result->income,
            'spent' => $result->spent,
            'paid' => $result->paidThisMonth,
            'over_envelopes' => collect($result->envelopes)
                ->filter(fn (EnvelopeStatus $e) => $e->state === EnvelopeStatus::STATE_OVER)
                ->map(fn (EnvelopeStatus $e) => ['name' => $e->name, 'over' => Money::sub('0', $e->remaining)])
                ->values()->all(),
            'debt_reduction' => Money::sub($debtPaid, $cardCharges),
            'savings_target' => $result->savings,
            'saved' => $saved,
        ];
    }
}
