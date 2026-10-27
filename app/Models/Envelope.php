<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sobre de presupuesto dentro de un periodo.
 */
class Envelope extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'period_id',
        'name',
        'allocated',
        'sort_order',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'allocated' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
