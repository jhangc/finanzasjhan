<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Avance de una deuda en cuotas: cuánto se pagó, en qué cuota va y si va al día.
 * Las cuotas pueden ser mensuales, quincenales (cada 15 días) o semanales.
 * Todo se calcula desde el monto original, el saldo actual y el calendario de cuotas.
 */
class DebtProgress
{
    /**
     * @return array{
     *     original: string, paid: string, remaining: string, percent: int,
     *     term: int, installments_due: int, current_installment: int, months_left: int,
     *     first_due: ?CarbonImmutable, end_date: ?CarbonImmutable, next_due: ?CarbonImmutable,
     *     installment: string, frequency: string,
     *     expected_paid: string, difference: string, status: string,
     *     required_monthly: string, finished: bool
     * }|null
     */
    public function for(Commitment $commitment, ?CarbonInterface $today = null): ?array
    {
        if (! $commitment->hasTerm()) {
            return null;
        }

        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $frequency = $commitment->frequency ?: 'monthly';
        $installment = Money::of($commitment->installment());
        $original = Money::of($commitment->original_amount);
        $remaining = Money::positive($commitment->total_balance);
        $paid = Money::positive(Money::sub($original, $remaining));
        $term = max(1, $commitment->term_months);

        $firstDue = $this->firstDue($commitment, $frequency);
        $schedule = fn (int $n) => $this->dueNumber($firstDue, $frequency, $commitment->pay_day, $n);

        $installmentsDue = $firstDue ? $this->installmentsDue($schedule, $firstDue, $term, $today) : 0;
        $installmentsLeft = max(0, $term - $installmentsDue);
        $finished = Money::cmp($remaining, '0') <= 0;

        // Lo que ya debería estar pagado: la inicial + cada cuota vencida (sin pasar del total).
        $down = Money::of($commitment->down_payment);
        $expected = Money::add($down, Money::mul($installment, (string) $installmentsDue));
        if ($finished || Money::cmp($expected, $original) > 0) {
            $expected = $finished ? $paid : $original;
        }
        $difference = Money::sub($paid, $expected);

        $status = match (true) {
            $finished => 'finished',
            Money::cmp($difference, '0') > 0 => 'ahead',
            Money::cmp($difference, '0') < 0 => 'behind',
            default => 'on_track',
        };

        // Cuánto hay que pagar por cuota para terminar dentro del plazo (al menos la cuota).
        $required = $finished ? '0.00' : ($installmentsLeft > 0
            ? Money::max(Money::div($remaining, (string) $installmentsLeft), $installment)
            : $remaining);

        // Si solo se paga la cuota, lo que quedaría al terminar (iría a hipoteca/refinanciamiento).
        $projectedFinal = $finished ? '0.00'
            : Money::positive(Money::sub($remaining, Money::mul($installment, (string) $installmentsLeft)));

        return [
            'original' => $original,
            'down_payment' => $down,
            'financed' => Money::positive(Money::sub($original, $down)),
            'paid' => $paid,
            'remaining' => $remaining,
            'percent' => min(100, Money::percent($paid, $original)),
            'term' => $term,
            'installments_due' => $installmentsDue,
            'current_installment' => min($term, $installmentsDue + 1),
            'months_left' => $installmentsLeft,
            'first_due' => $firstDue,
            'end_date' => $firstDue ? $schedule($term) : null,
            'next_due' => $firstDue && ! $finished && $installmentsDue < $term ? $schedule($installmentsDue + 1) : null,
            'installment' => $installment,
            'frequency' => $frequency,
            'expected_paid' => $expected,
            'difference' => $difference,
            'status' => $status,
            'required_monthly' => $required,
            'minimum' => $installment,
            'extra_paid' => Money::positive($difference),
            'projected_final' => $projectedFinal,
            'needs_balloon' => Money::cmp($projectedFinal, '0') > 0,
            'finished' => $finished,
        ];
    }

    /** Fecha de la primera cuota (en mensuales, ajustada al día de pago si existe). */
    private function firstDue(Commitment $commitment, string $frequency): ?CarbonImmutable
    {
        if ($commitment->start_date === null) {
            return null;
        }

        $start = CarbonImmutable::instance($commitment->start_date)->startOfDay();

        if ($frequency !== 'monthly' || $commitment->pay_day === null) {
            return $start;
        }

        $candidate = DueDates::inMonth($commitment->pay_day, $start);

        return $candidate->lessThan($start) ? DueDates::inMonth($commitment->pay_day, $start->addMonthNoOverflow()) : $candidate;
    }

    /** Fecha de la cuota número $n (1 = primera). */
    private function dueNumber(CarbonImmutable $firstDue, string $frequency, ?int $payDay, int $n): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $firstDue->addWeeks($n - 1),
            'biweekly' => $firstDue->addDays(15 * ($n - 1)),
            default => DueDates::inMonth($payDay ?? $firstDue->day, $firstDue->startOfMonth()->addMonthsNoOverflow($n - 1)),
        };
    }

    /** Cuántas cuotas ya vencieron hasta hoy (incluida la de hoy). */
    private function installmentsDue(callable $schedule, CarbonImmutable $firstDue, int $term, CarbonImmutable $today): int
    {
        if ($today->lessThan($firstDue)) {
            return 0;
        }

        $count = 0;
        while ($count < $term && $schedule($count + 1)->lessThanOrEqualTo($today)) {
            $count++;
        }

        return $count;
    }
}
