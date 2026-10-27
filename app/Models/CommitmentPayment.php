<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Observers\CommitmentPaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(CommitmentPaymentObserver::class)]
class CommitmentPayment extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'commitment_id',
        'period_id',
        'amount',
        'paid_at',
        'note',
        'account_id',
        'paid_with_commitment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /** Cuenta de débito de donde salió el dinero. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Tarjeta con la que se pagó (ej. Netflix pagado con la Visa). */
    public function paidWith(): BelongsTo
    {
        return $this->belongsTo(Commitment::class, 'paid_with_commitment_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class)->orderBy('id');
    }

    /** Datos para el visor de vouchers. */
    public function voucherGallery(): array
    {
        return $this->vouchers->map(fn (PaymentVoucher $v) => [
            'url' => route('vouchers.show', $v),
            'pdf' => $v->isPdf(),
            'name' => $v->original_name ?: 'Voucher',
        ])->values()->all();
    }
}
