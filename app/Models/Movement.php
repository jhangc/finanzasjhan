<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Observers\MovementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gasto registrado en un sobre.
 */
#[ObservedBy(MovementObserver::class)]
class Movement extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'envelope_id',
        'amount',
        'spent_at',
        'note',
        'paid_with_commitment_id',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'datetime',
        ];
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function paidWith(): BelongsTo
    {
        return $this->belongsTo(Commitment::class, 'paid_with_commitment_id');
    }

    /** Cuenta de débito de donde salió el dinero. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
