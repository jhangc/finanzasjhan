<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deuda, gasto fijo mensual o gasto anual prorrateado.
 */
class Commitment extends Model
{
    use BelongsToUser, HasFactory;

    public const TYPE_DEBT = 'debt';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_ANNUAL = 'annual';

    /** Periodo de las cuotas de un préstamo. */
    public const FREQUENCIES = [
        'monthly' => 'Mensual',
        'biweekly' => 'Quincenal (cada 15 días)',
        'weekly' => 'Semanal',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'total_balance',
        'credit_limit',
        'statement_balance',
        'statement_date',
        'original_amount',
        'down_payment',
        'term_months',
        'installment_amount',
        'frequency',
        'start_date',
        'monthly_amount',
        'pay_day',
        'cut_day',
        'interest_rate',
        'is_card',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'total_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'statement_date' => 'date',
            'original_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'term_months' => 'integer',
            'installment_amount' => 'decimal:2',
            'start_date' => 'date',
            'monthly_amount' => 'decimal:2',
            'pay_day' => 'integer',
            'cut_day' => 'integer',
            'interest_rate' => 'decimal:3',
            'is_card' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CommitmentPayment::class);
    }

    /** Gastos de sobres pagados con esta tarjeta. */
    public function cardMovements(): HasMany
    {
        return $this->hasMany(Movement::class, 'paid_with_commitment_id');
    }

    public function recurringRules(): HasMany
    {
        return $this->hasMany(RecurringRule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function isDebt(): bool
    {
        return $this->type === self::TYPE_DEBT;
    }

    /** Deuda a plazo fijo (departamento, préstamo en cuotas). */
    /** Monto de cada cuota (en préstamos antiguos, la cuota mensual). */
    public function installment(): string
    {
        return (string) ($this->installment_amount ?? $this->monthly_amount);
    }

    public function hasTerm(): bool
    {
        return $this->isDebt() && ! $this->is_card && $this->original_amount !== null && $this->term_months !== null;
    }
}
