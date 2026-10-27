<?php

namespace App\Services\Charts;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Datos para los tres gráficos. Todo se calcula con consultas agregadas; nada se guarda.
 */
class ChartData
{
    public const TYPES = ['envelopes', 'monthly', 'debt'];

    private const PALETTE = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Dona: gasto del mes por sobre. */
    public function envelopes(?Period $period): array
    {
        if ($period === null) {
            return ['labels' => [], 'data' => [], 'colors' => []];
        }

        $envelopes = $period->envelopes()->withSum('movements', 'amount')->get();

        return [
            'labels' => $envelopes->pluck('name')->all(),
            'data' => $envelopes->map(fn ($e) => Money::of($e->movements_sum_amount))->all(),
            'colors' => $envelopes->values()->map(fn ($e, $i) => $e->color ?: self::PALETTE[$i % count(self::PALETTE)])->all(),
        ];
    }

    /** Barras agrupadas: ingresos, gastos en sobres y pagos de deuda de los últimos meses. */
    public function monthly(User $user, int $months = 6, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? now());
        $range = $this->monthRange($today, $months);

        $periods = $user->periods()
            ->where(fn ($q) => $range->each(fn ($m) => $q->orWhere(fn ($q) => $q->where('year', $m->year)->where('month', $m->month))))
            ->get()
            ->keyBy(fn (Period $p) => $p->year.'-'.$p->month);

        $ids = $periods->pluck('id');

        $confirmed = Income::whereIn('period_id', $ids)->where('confirmed', true)
            ->selectRaw('period_id, currency, SUM(amount) as total')->groupBy('period_id', 'currency')->get()
            ->groupBy('period_id');

        $spent = Movement::query()->join('envelopes', 'envelopes.id', '=', 'movements.envelope_id')
            ->whereIn('envelopes.period_id', $ids)
            ->selectRaw('envelopes.period_id, SUM(movements.amount) as total')->groupBy('envelopes.period_id')
            ->pluck('total', 'envelopes.period_id');

        $debtPayments = CommitmentPayment::query()->join('commitments', 'commitments.id', '=', 'commitment_payments.commitment_id')
            ->whereIn('commitment_payments.period_id', $ids)->where('commitments.type', Commitment::TYPE_DEBT)
            ->selectRaw('commitment_payments.period_id, SUM(commitment_payments.amount) as total')
            ->groupBy('commitment_payments.period_id')
            ->pluck('total', 'commitment_payments.period_id');

        $rate = $user->usd_rate;

        $rows = $range->map(function (CarbonImmutable $month) use ($periods, $confirmed, $spent, $debtPayments, $rate) {
            $period = $periods->get($month->year.'-'.$month->month);

            $income = '0.00';
            if ($period) {
                $byCurrency = $confirmed->get($period->id);
                $income = $byCurrency
                    ? Money::sum($byCurrency->map(fn ($row) => $row->currency === 'USD' && $rate !== null ? Money::mul($row->total, $rate) : $row->total))
                    : Money::of($period->expected_income);
            }

            return [
                'label' => $this->label($month),
                'income' => $income,
                'spent' => Money::of($period ? $spent->get($period->id) : 0),
                'debt' => Money::of($period ? $debtPayments->get($period->id) : 0),
            ];
        });

        return [
            'labels' => $rows->pluck('label')->all(),
            'income' => $rows->pluck('income')->all(),
            'spent' => $rows->pluck('spent')->all(),
            'debt' => $rows->pluck('debt')->all(),
        ];
    }

    /**
     * Línea: deuda total al cierre de cada mes. Se reconstruye hacia atrás desde el saldo
     * actual deshaciendo los cargos con tarjeta y los pagos posteriores a cada cierre.
     */
    public function debt(User $user, int $months = 6, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? now());
        $debtIds = Commitment::ownedBy($user)->where('type', Commitment::TYPE_DEBT)->pluck('id');
        $current = Money::of(Commitment::whereIn('id', $debtIds)->sum('total_balance'));

        $points = $this->monthRange($today, $months)->map(function (CarbonImmutable $month) use ($today, $debtIds, $current) {
            $isCurrent = $month->isSameMonth($today);
            $cutoff = $month->endOfMonth();

            if ($isCurrent) {
                return ['label' => $this->label($month), 'value' => $current];
            }

            $chargesAfter = Movement::whereIn('paid_with_commitment_id', $debtIds)->where('spent_at', '>', $cutoff)->sum('amount');
            $paymentsAfter = CommitmentPayment::whereIn('commitment_id', $debtIds)->where('paid_at', '>', $cutoff->toDateString())->sum('amount');

            return [
                'label' => $this->label($month),
                'value' => Money::add(Money::sub($current, $chargesAfter), $paymentsAfter),
            ];
        });

        return [
            'labels' => $points->pluck('label')->all(),
            'data' => $points->pluck('value')->all(),
        ];
    }

    /** Los últimos $months meses terminando en el de $today, del más antiguo al más reciente. */
    private function monthRange(CarbonImmutable $today, int $months)
    {
        $start = $today->startOfMonth();

        return collect(range($months - 1, 0))->map(fn ($back) => $start->subMonthsNoOverflow($back));
    }

    private function label(CarbonImmutable $month): string
    {
        return self::MONTHS[$month->month - 1].' '.$month->format('y');
    }
}
