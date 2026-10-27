<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plantilla que GenerateMonthlyPeriod aplica al armar el mes nuevo.
 */
class RecurringRule extends Model
{
    use BelongsToUser, HasFactory;

    public const KIND_ENVELOPE = 'envelope';

    public const KIND_COMMITMENT_DUE = 'commitment_due';

    protected $fillable = [
        'user_id',
        'name',
        'kind',
        'commitment_id',
        'amount',
        'day_of_month',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }
}
