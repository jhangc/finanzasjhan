<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante (foto o PDF) de un pago. El archivo vive en el disco privado.
 */
class PaymentVoucher extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'commitment_payment_id',
        'path',
        'original_name',
    ];

    protected $hidden = ['path'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CommitmentPayment::class, 'commitment_payment_id');
    }

    public function isPdf(): bool
    {
        return str_ends_with(strtolower($this->path), '.pdf');
    }
}
