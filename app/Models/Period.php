<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Mes presupuestario.
 */
class Period extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'expected_income',
        'savings_target',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'expected_income' => 'decimal:2',
            'savings_target' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class)->orderBy('sort_order');
    }

    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(Movement::class, Envelope::class);
    }

    public function commitmentPayments(): HasMany
    {
        return $this->hasMany(CommitmentPayment::class);
    }
}
