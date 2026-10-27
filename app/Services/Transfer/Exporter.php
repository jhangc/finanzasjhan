<?php

namespace App\Services\Transfer;

use App\Models\Commitment;
use App\Models\Envelope;
use App\Models\Movement;
use App\Models\Period;
use App\Models\RecurringRule;
use App\Models\User;
use App\Support\Money;

class Exporter
{
    public const VERSION = 1;

    public const CSV_HEADER = ['fecha', 'categoria', 'monto', 'nota', 'tarjeta'];

    /** Todos los datos del usuario. Las relaciones entre registros usan "ref" (el id original). */
    public function json(User $user): array
    {
        $periods = $user->periods()
            ->with(['incomes', 'commitmentPayments', 'envelopes.movements'])
            ->orderBy('year')->orderBy('month')
            ->get();

        return [
            'app' => 'FinControl',
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'settings' => [
                'currency_default' => $user->currency_default,
                'usd_rate' => $user->usd_rate,
                'reminder_email' => $user->reminder_email,
                'default_card_ref' => $user->default_card_id,
            ],
            'commitments' => $user->commitments()->orderBy('id')->get()->map(fn (Commitment $c) => [
                'ref' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'total_balance' => $c->total_balance,
                'credit_limit' => $c->credit_limit,
                'original_amount' => $c->original_amount,
                'down_payment' => $c->down_payment,
                'term_months' => $c->term_months,
                'start_date' => $c->start_date?->toDateString(),
                'monthly_amount' => $c->monthly_amount,
                'pay_day' => $c->pay_day,
                'cut_day' => $c->cut_day,
                'interest_rate' => $c->interest_rate,
                'is_card' => $c->is_card,
                'active' => $c->active,
            ])->all(),
            'accounts' => $user->accounts()->orderBy('sort_order')->get()->map(fn ($a) => [
                'name' => $a->name,
                'bank' => $a->bank,
                'type' => $a->type,
                'currency' => $a->currency,
                'balance' => $a->balance,
                'color' => $a->color,
            ])->all(),
            'recurring_rules' => $user->recurringRules()->orderBy('id')->get()->map(fn (RecurringRule $r) => [
                'name' => $r->name,
                'kind' => $r->kind,
                'commitment_ref' => $r->commitment_id,
                'amount' => $r->amount,
                'day_of_month' => $r->day_of_month,
                'active' => $r->active,
            ])->all(),
            'periods' => $periods->map(fn (Period $p) => [
                'year' => $p->year,
                'month' => $p->month,
                'expected_income' => $p->expected_income,
                'savings_target' => $p->savings_target,
                'closed_at' => $p->closed_at?->toIso8601String(),
                'incomes' => $p->incomes->map(fn ($i) => [
                    'amount' => $i->amount,
                    'currency' => $i->currency,
                    'source' => $i->source,
                    'received_at' => $i->received_at->toDateString(),
                    'confirmed' => $i->confirmed,
                ])->all(),
                'envelopes' => $p->envelopes->map(fn (Envelope $e) => [
                    'name' => $e->name,
                    'allocated' => $e->allocated,
                    'sort_order' => $e->sort_order,
                    'color' => $e->color,
                    'movements' => $e->movements->map(fn (Movement $m) => [
                        'amount' => $m->amount,
                        'spent_at' => $m->spent_at->format('Y-m-d H:i:s'),
                        'note' => $m->note,
                        'card_ref' => $m->paid_with_commitment_id,
                    ])->all(),
                ])->all(),
                'payments' => $p->commitmentPayments->map(fn ($pay) => [
                    'commitment_ref' => $pay->commitment_id,
                    'amount' => $pay->amount,
                    'paid_at' => $pay->paid_at->toDateString(),
                    'note' => $pay->note,
                ])->all(),
            ])->all(),
        ];
    }

    /** CSV de movimientos (todos los meses). */
    public function csv(User $user): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::CSV_HEADER, escape: '');

        Movement::ownedBy($user)
            ->with(['envelope:id,name', 'paidWith:id,name'])
            ->orderBy('spent_at')
            ->each(function (Movement $m) use ($handle) {
                fputcsv($handle, [
                    $m->spent_at->format('Y-m-d H:i:s'),
                    $m->envelope->name,
                    Money::of($m->amount),
                    (string) $m->note,
                    (string) $m->paidWith?->name,
                ], escape: '');
            });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
