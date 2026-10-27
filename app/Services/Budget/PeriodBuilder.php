<?php

namespace App\Services\Budget;

use App\Models\Commitment;
use App\Models\Envelope;
use App\Models\Period;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\Parsing\QuickEntryParser;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arma el mes: copia sobres del mes anterior y aplica las reglas recurrentes.
 */
class PeriodBuilder
{
    public function current(User $user, ?CarbonInterface $today = null): ?Period
    {
        $today ??= now();

        return $user->periods()->where('year', $today->year)->where('month', $today->month)->first();
    }

    /** El periodo más reciente anterior a year/month. */
    public function previous(User $user, int $year, int $month): ?Period
    {
        return $user->periods()
            ->where(fn ($q) => $q->where('year', '<', $year)
                ->orWhere(fn ($q) => $q->where('year', $year)->where('month', '<', $month)))
            ->orderByDesc('year')->orderByDesc('month')
            ->first();
    }

    /**
     * Valores iniciales del formulario cuando el mes aún no existe.
     *
     * @return array{expected_income: string, savings_target: string, envelopes: list<array>}
     */
    public function draft(User $user, CarbonInterface $month): array
    {
        $previous = $this->previous($user, $month->year, $month->month);

        $envelopes = $previous?->envelopes->map(fn (Envelope $e) => [
            'id' => null,
            'name' => $e->name,
            'allocated' => Money::of($e->allocated),
            'color' => $e->color,
        ])->values()->all() ?? [];

        $ruleNames = collect($envelopes)->pluck('name')->map(QuickEntryParser::normalize(...));
        $user->recurringRules()->where('active', true)->where('kind', RecurringRule::KIND_ENVELOPE)->get()
            ->reject(fn (RecurringRule $r) => $ruleNames->contains(QuickEntryParser::normalize($r->name)))
            ->each(function (RecurringRule $rule) use (&$envelopes) {
                $envelopes[] = ['id' => null, 'name' => $rule->name, 'allocated' => Money::of($rule->amount), 'color' => null];
            });

        return [
            'expected_income' => Money::of($previous?->expected_income),
            'savings_target' => Money::of($previous?->savings_target),
            'envelopes' => $envelopes,
        ];
    }

    /**
     * Crea el periodo del mes (idempotente): cierra el anterior, copia sobres
     * con la misma asignación (el sobrante no se arrastra) y aplica reglas.
     */
    public function generate(User $user, int $year, int $month): Period
    {
        return DB::transaction(function () use ($user, $year, $month) {
            $existing = $user->periods()->where('year', $year)->where('month', $month)->first();
            if ($existing) {
                return $existing;
            }

            $previous = $this->previous($user, $year, $month);

            $period = $user->periods()->create([
                'year' => $year,
                'month' => $month,
                'expected_income' => Money::of($previous?->expected_income),
                'savings_target' => Money::of($previous?->savings_target),
            ]);

            if ($previous) {
                $this->copyEnvelopes($previous, $period);
                $user->periods()->whereKeyNot($period->id)->whereNull('closed_at')
                    ->where(fn ($q) => $q->where('year', '<', $year)
                        ->orWhere(fn ($q) => $q->where('year', $year)->where('month', '<', $month)))
                    ->update(['closed_at' => now()]);
            }

            $this->applyRules($user, $period);

            return $period->fresh();
        });
    }

    /** Categorías sugeridas al crear las primeras. */
    public const DEFAULT_CATEGORIES = ['Comida', 'Reparación de moto', 'Gasolina', 'Apuestas', 'Diversión', 'Fútbol', 'Ropa', 'Calzado'];

    private const PALETTE = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

    /**
     * Agrega categorías al periodo, saltando las que ya existen (por nombre).
     *
     * @param  list<array{name: string, allocated?: ?string}>  $categories
     * @return int Cuántas creó.
     */
    public function addEnvelopes(Period $period, array $categories): int
    {
        $existing = $period->envelopes()->pluck('name')->map(QuickEntryParser::normalize(...));
        $order = (int) $period->envelopes()->max('sort_order');
        $count = $period->envelopes()->count();
        $created = 0;

        foreach ($categories as $category) {
            $name = trim($category['name']);
            if ($name === '' || $existing->contains(QuickEntryParser::normalize($name))) {
                continue;
            }

            $period->envelopes()->create([
                'user_id' => $period->user_id,
                'name' => $name,
                'allocated' => Money::of($category['allocated'] ?? '0'),
                'color' => self::PALETTE[($count + $created) % count(self::PALETTE)],
                'sort_order' => ++$order,
            ]);
            $existing->push(QuickEntryParser::normalize($name));
            $created++;
        }

        return $created;
    }

    /** Copia los sobres que falten (por nombre). Devuelve cuántos copió. */
    public function copyEnvelopes(Period $from, Period $to): int
    {
        $existing = $to->envelopes()->pluck('name')->map(QuickEntryParser::normalize(...));
        $order = (int) $to->envelopes()->max('sort_order');
        $copied = 0;

        foreach ($from->envelopes as $envelope) {
            if ($existing->contains(QuickEntryParser::normalize($envelope->name))) {
                continue;
            }

            $to->envelopes()->create([
                'user_id' => $to->user_id,
                'name' => $envelope->name,
                'allocated' => $envelope->allocated,
                'color' => $envelope->color,
                'sort_order' => $existing->isEmpty() && $copied === 0 ? $envelope->sort_order : ++$order,
            ]);
            $copied++;
        }

        return $copied;
    }

    /**
     * envelope: crea el sobre si no existe.
     * commitment_due: fija la cuota (y el día) del compromiso para el mes nuevo.
     */
    public function applyRules(User $user, Period $period): void
    {
        $rules = $user->recurringRules()->where('active', true)->get();

        foreach ($rules as $rule) {
            if ($rule->kind === RecurringRule::KIND_ENVELOPE) {
                $exists = $period->envelopes()->get()
                    ->contains(fn (Envelope $e) => QuickEntryParser::normalize($e->name) === QuickEntryParser::normalize($rule->name));

                if (! $exists) {
                    $period->envelopes()->create([
                        'user_id' => $user->id,
                        'name' => $rule->name,
                        'allocated' => $rule->amount,
                        'sort_order' => (int) $period->envelopes()->max('sort_order') + 1,
                    ]);
                }

                continue;
            }

            $commitment = $rule->commitment_id ? Commitment::ownedBy($user)->find($rule->commitment_id) : null;
            $commitment?->update(array_filter([
                'monthly_amount' => $rule->amount,
                'pay_day' => $rule->day_of_month,
                'active' => true,
            ], fn ($value) => $value !== null));
        }
    }

    /**
     * Guarda la pantalla "Armar el mes": crea o actualiza el periodo y sincroniza sus sobres.
     *
     * @param  array{expected_income: string, savings_target: string, envelopes?: list<array>}  $data
     */
    public function saveSetup(User $user, CarbonInterface $month, array $data): Period
    {
        return DB::transaction(function () use ($user, $month, $data) {
            $period = $user->periods()->firstOrNew(['year' => $month->year, 'month' => $month->month]);
            $isNew = ! $period->exists;
            $period->fill([
                'expected_income' => Money::of($data['expected_income']),
                'savings_target' => Money::of($data['savings_target']),
            ])->save();

            if ($isNew) {
                $user->periods()->whereKeyNot($period->id)->whereNull('closed_at')
                    ->where(fn ($q) => $q->where('year', '<', $month->year)
                        ->orWhere(fn ($q) => $q->where('year', $month->year)->where('month', '<', $month->month)))
                    ->update(['closed_at' => now()]);
            }

            $rows = collect($data['envelopes'] ?? [])->values();
            $keptIds = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id);

            $toDelete = $period->envelopes()->whereNotIn('id', $keptIds)->withCount('movements')->get();
            $withMovements = $toDelete->where('movements_count', '>', 0);
            if ($withMovements->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'envelopes' => 'No puedes quitar categorías con gastos registrados: '.$withMovements->pluck('name')->join(', ').'.',
                ]);
            }
            $toDelete->each->delete();

            foreach ($rows as $index => $row) {
                $attributes = [
                    'name' => trim($row['name']),
                    'allocated' => Money::of($row['allocated']),
                    'color' => $row['color'] ?? null,
                    'sort_order' => $index,
                ];

                if (! empty($row['id'])) {
                    $period->envelopes()->whereKey($row['id'])->firstOrFail()->update($attributes);
                } else {
                    $period->envelopes()->create($attributes + ['user_id' => $user->id]);
                }
            }

            return $period->fresh();
        });
    }

    public static function monthOf(Period $period): CarbonImmutable
    {
        return CarbonImmutable::create($period->year, $period->month, 1);
    }
}
