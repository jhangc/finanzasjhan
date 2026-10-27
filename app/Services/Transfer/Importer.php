<?php

namespace App\Services\Transfer;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Envelope;
use App\Models\Income;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;
use App\Services\Parsing\QuickEntryParser;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Importa un respaldo JSON completo o un CSV de movimientos.
 * Lo que ya existe no se duplica.
 */
class Importer
{
    /**
     * El JSON trae los saldos ya calculados, así que se guarda sin disparar
     * los observers (si no, los cargos y pagos se aplicarían dos veces).
     *
     * @return array<string, int>
     */
    public function json(User $user, string $contents): array
    {
        $data = json_decode($contents, true);

        if (! is_array($data) || ($data['app'] ?? null) !== 'FinControl' || ! isset($data['periods'])) {
            throw ValidationException::withMessages(['file' => 'El archivo no es un respaldo de FinControl.']);
        }

        $counts = ['commitments' => 0, 'accounts' => 0, 'periods' => 0, 'envelopes' => 0, 'movements' => 0, 'incomes' => 0, 'payments' => 0, 'rules' => 0];

        try {
            DB::transaction(function () use ($user, $data, &$counts) {
                $commitmentIds = [];
                foreach ($data['commitments'] ?? [] as $row) {
                    $commitment = Commitment::ownedBy($user)->where('name', $row['name'])->first();
                    if (! $commitment) {
                        $commitment = $user->commitments()->create(Arr::only($row, [
                            'name', 'type', 'total_balance', 'credit_limit', 'original_amount', 'down_payment', 'term_months',
                            'start_date', 'monthly_amount', 'pay_day', 'cut_day', 'interest_rate', 'is_card', 'active',
                        ]));
                        $counts['commitments']++;
                    }
                    $commitmentIds[$row['ref']] = $commitment->id;
                }

                foreach ($data['accounts'] ?? [] as $row) {
                    $account = $user->accounts()->firstOrCreate(
                        ['name' => $row['name'], 'bank' => $row['bank'] ?? null],
                        Arr::only($row, ['type', 'currency', 'balance', 'color']),
                    );
                    $counts['accounts'] += (int) $account->wasRecentlyCreated;
                }

                foreach ($data['recurring_rules'] ?? [] as $row) {
                    $rule = $user->recurringRules()->firstOrCreate(
                        ['name' => $row['name'], 'kind' => $row['kind']],
                        [
                            'commitment_id' => $commitmentIds[$row['commitment_ref'] ?? null] ?? null,
                            'amount' => $row['amount'],
                            'day_of_month' => $row['day_of_month'] ?? null,
                            'active' => $row['active'] ?? true,
                        ],
                    );
                    $counts['rules'] += (int) $rule->wasRecentlyCreated;
                }

                foreach ($data['periods'] as $row) {
                    $period = $user->periods()->firstOrCreate(
                        ['year' => $row['year'], 'month' => $row['month']],
                        Arr::only($row, ['expected_income', 'savings_target', 'closed_at']),
                    );
                    $counts['periods'] += (int) $period->wasRecentlyCreated;

                    foreach ($row['incomes'] ?? [] as $income) {
                        $attributes = ['period_id' => $period->id] + Arr::only($income, ['amount', 'currency', 'source', 'received_at', 'confirmed']);
                        if (! $this->incomeExists($user, $attributes)) {
                            $user->incomes()->create($attributes);
                            $counts['incomes']++;
                        }
                    }

                    foreach ($row['envelopes'] ?? [] as $envelopeRow) {
                        $envelope = $this->envelope($period, $envelopeRow['name'], $envelopeRow);
                        $counts['envelopes'] += (int) $envelope->wasRecentlyCreated;

                        foreach ($envelopeRow['movements'] ?? [] as $movement) {
                            $created = $this->movement($envelope, [
                                'amount' => $movement['amount'],
                                'spent_at' => $movement['spent_at'],
                                'note' => $movement['note'] ?? null,
                                'paid_with_commitment_id' => $commitmentIds[$movement['card_ref'] ?? null] ?? null,
                            ], quietly: true);
                            $counts['movements'] += (int) $created;
                        }
                    }

                    foreach ($row['payments'] ?? [] as $payment) {
                        $commitmentId = $commitmentIds[$payment['commitment_ref']] ?? null;
                        if ($commitmentId === null) {
                            continue;
                        }

                        $attributes = [
                            'user_id' => $user->id,
                            'commitment_id' => $commitmentId,
                            'period_id' => $period->id,
                            'amount' => $payment['amount'],
                            'paid_at' => $payment['paid_at'],
                            'note' => $payment['note'] ?? null,
                        ];

                        $exists = CommitmentPayment::where(Arr::only($attributes, ['user_id', 'commitment_id', 'period_id']))
                            ->whereDate('paid_at', $attributes['paid_at'])
                            ->get()->contains(fn ($p) => Money::cmp($p->amount, $attributes['amount']) === 0);

                        if (! $exists) {
                            (new CommitmentPayment($attributes))->saveQuietly();
                            $counts['payments']++;
                        }
                    }
                }

                $settings = $data['settings'] ?? [];
                $user->update(array_filter([
                    'currency_default' => $settings['currency_default'] ?? null,
                    'usd_rate' => $settings['usd_rate'] ?? null,
                    'reminder_email' => $settings['reminder_email'] ?? null,
                    'default_card_id' => $commitmentIds[$settings['default_card_ref'] ?? null] ?? null,
                ], fn ($v) => $v !== null));
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'No se pudo importar el respaldo: el archivo tiene datos inválidos.']);
        }

        return $counts;
    }

    /**
     * CSV de movimientos con columnas fecha, categoria, monto[, nota, tarjeta].
     * Acepta también "sobre" como nombre de la columna de categoría (formato anterior).
     * Son gastos nuevos: si traen tarjeta, suben su saldo como cualquier gasto.
     *
     * @return array<string, int>
     */
    public function csv(User $user, string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\n|\r/', trim($contents));
        $delimiter = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : ',';

        $header = array_map(
            fn ($h) => QuickEntryParser::normalize($h) === 'sobre' ? 'categoria' : QuickEntryParser::normalize($h),
            str_getcsv(array_shift($lines) ?? '', $delimiter, escape: ''),
        );
        foreach (['fecha', 'categoria', 'monto'] as $required) {
            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages(['file' => 'El CSV debe tener las columnas: fecha, categoria, monto (y opcionalmente nota, tarjeta).']);
            }
        }

        $cards = Commitment::ownedBy($user)->where('is_card', true)->get()
            ->keyBy(fn ($c) => QuickEntryParser::normalize($c->name));
        $counts = ['movements' => 0, 'skipped' => 0, 'envelopes' => 0, 'periods' => 0];

        DB::transaction(function () use ($user, $lines, $delimiter, $header, $cards, &$counts) {
            foreach ($lines as $number => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $row = array_combine($header, array_pad(str_getcsv($line, $delimiter, escape: ''), count($header), ''));
                $amount = str_replace(',', '.', trim($row['monto']));

                try {
                    $date = CarbonImmutable::parse(trim($row['fecha']));
                } catch (Throwable) {
                    $date = null;
                }

                if ($date === null || trim($row['categoria']) === '' || ! is_numeric($amount)
                    || Money::cmp($amount, '0.01') < 0 || Money::cmp($amount, '999999.99') > 0) {
                    throw ValidationException::withMessages(['file' => 'Fila '.($number + 2).' inválida: revisa fecha, categoría y monto.']);
                }

                $period = $user->periods()->firstOrCreate(['year' => $date->year, 'month' => $date->month]);
                $counts['periods'] += (int) $period->wasRecentlyCreated;

                $envelope = $this->envelope($period, trim($row['categoria']));
                $counts['envelopes'] += (int) $envelope->wasRecentlyCreated;

                $card = $cards->get(QuickEntryParser::normalize($row['tarjeta'] ?? ''));

                $created = $this->movement($envelope, [
                    'amount' => Money::of($amount),
                    'spent_at' => $date->format('Y-m-d H:i:s'),
                    'note' => trim($row['nota'] ?? '') ?: null,
                    'paid_with_commitment_id' => $card?->id,
                ]);

                $counts[$created ? 'movements' : 'skipped']++;
            }
        });

        return $counts;
    }

    private function envelope(Period $period, string $name, array $attributes = []): Envelope
    {
        $existing = $period->envelopes()->get()
            ->first(fn (Envelope $e) => QuickEntryParser::normalize($e->name) === QuickEntryParser::normalize($name));

        return $existing ?? $period->envelopes()->create([
            'user_id' => $period->user_id,
            'name' => $name,
            'allocated' => $attributes['allocated'] ?? '0',
            'sort_order' => $attributes['sort_order'] ?? ((int) $period->envelopes()->max('sort_order') + 1),
            'color' => $attributes['color'] ?? null,
        ]);
    }

    /** Crea el movimiento si no existe uno idéntico. */
    private function movement(Envelope $envelope, array $attributes, bool $quietly = false): bool
    {
        $spentAt = CarbonImmutable::parse($attributes['spent_at'])->format('Y-m-d H:i:s');

        $exists = $envelope->movements()->where('spent_at', $spentAt)->get()
            ->contains(fn (Movement $m) => Money::cmp($m->amount, $attributes['amount']) === 0
                && (string) $m->note === (string) $attributes['note']);

        if ($exists) {
            return false;
        }

        $movement = new Movement(['user_id' => $envelope->user_id, 'envelope_id' => $envelope->id, 'spent_at' => $spentAt] + $attributes);
        $quietly ? $movement->saveQuietly() : $movement->save();

        return true;
    }

    private function incomeExists(User $user, array $attributes): bool
    {
        return Income::ownedBy($user)
            ->where('period_id', $attributes['period_id'])
            ->where('source', $attributes['source'])
            ->whereDate('received_at', $attributes['received_at'])
            ->get()
            ->contains(fn (Income $i) => Money::cmp($i->amount, $attributes['amount']) === 0);
    }
}
