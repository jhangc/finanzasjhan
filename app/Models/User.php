<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'currency_default',
        'usd_rate',
        'reminder_email',
        'default_card_id',
        'default_account_id',
        'income_sources',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'usd_rate' => 'decimal:3',
            'income_sources' => 'array',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    public function commitmentPayments(): HasMany
    {
        return $this->hasMany(CommitmentPayment::class);
    }

    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function recurringRules(): HasMany
    {
        return $this->hasMany(RecurringRule::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function defaultCard(): BelongsTo
    {
        return $this->belongsTo(Commitment::class, 'default_card_id');
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public const DEFAULT_INCOME_SOURCES = ['Sueldo', 'Freelance', 'Venta', 'Apuestas', 'Devolución'];

    /** @return list<string> */
    public function incomeSourceList(): array
    {
        return $this->income_sources ?? self::DEFAULT_INCOME_SOURCES;
    }

    /** Agrega la categoría si no existe ya (sin importar mayúsculas). */
    public function addIncomeSource(string $name): void
    {
        $list = $this->incomeSourceList();
        if ($name === '' || in_array(mb_strtolower($name), array_map('mb_strtolower', $list), true)) {
            return;
        }

        $this->update(['income_sources' => [...$list, $name]]);
    }

    /** Los recordatorios van al correo de recordatorios si existe. */
    public function routeNotificationForMail(): string
    {
        return $this->reminder_email ?: $this->email;
    }
}
