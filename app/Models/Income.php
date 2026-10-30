<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Observers\IncomeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(IncomeObserver::class)]
class Income extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'period_id',
        'amount',
        'currency',
        'source',
        'received_at',
        'confirmed',
        'account_id',
        'from_account_id',
        'from_commitment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
            'confirmed' => 'boolean',
        ];
    }

    /** Movimiento entre cuentas propias o desde una tarjeta: mueve saldos pero no es ingreso del mes. */
    public function isTransfer(): bool
    {
        return $this->from_account_id !== null || $this->from_commitment_id !== null;
    }

    /** Solo ingresos reales (no movimientos entre cuentas). */
    public function scopeEarned(Builder $query): Builder
    {
        return $query->whereNull('from_account_id')->whereNull('from_commitment_id');
    }

    public function scopeTransfers(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('from_account_id')->orWhereNotNull('from_commitment_id'));
    }

    /** Cuenta a la que entró el dinero. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Cuenta de la que salió (transferencia entre cuentas propias). */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /** Tarjeta de la que salió (ej. disposición de efectivo): sube su deuda. */
    public function fromCard(): BelongsTo
    {
        return $this->belongsTo(Commitment::class, 'from_commitment_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
