<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta de débito: ahorro, sueldo/corriente o efectivo. El saldo lo actualiza el usuario.
 */
class Account extends Model
{
    use BelongsToUser, HasFactory;

    public const TYPES = [
        'savings' => 'Ahorros',
        'checking' => 'Sueldo / corriente',
        'cash' => 'Efectivo',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'bank',
        'type',
        'currency',
        'balance',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
