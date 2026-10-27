<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Observers\IncomeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
            'confirmed' => 'boolean',
        ];
    }

    /** Cuenta a la que entró el dinero. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
