<?php

namespace App\Services\Debts;

use App\Models\CommitmentPayment;
use App\Models\PaymentVoucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprobantes de pago en el disco privado (storage/app/private), una carpeta por usuario.
 * Nunca se sirven directo: pasan por una ruta que verifica al dueño.
 */
class VoucherStorage
{
    public const DISK = 'local';

    public const MAX_PER_PAYMENT = 10;

    public const FILE_RULES = ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'];

    /**
     * Agrega vouchers al pago.
     *
     * @param  array<UploadedFile>  $files
     */
    public function add(CommitmentPayment $payment, array $files): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $path = $file->storeAs("vouchers/{$payment->user_id}", $payment->id.'-'.Str::random(12).'.'.$extension, self::DISK);

            $payment->vouchers()->create([
                'user_id' => $payment->user_id,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            ]);
        }
    }

    /**
     * Quita los vouchers indicados (solo los de este pago).
     *
     * @param  array<int>  $ids
     */
    public function remove(CommitmentPayment $payment, array $ids): void
    {
        $payment->vouchers()->whereIn('id', $ids)->get()->each(function (PaymentVoucher $voucher) {
            Storage::disk(self::DISK)->delete($voucher->path);
            $voucher->delete();
        });
    }

    public function exists(PaymentVoucher $voucher): bool
    {
        return Storage::disk(self::DISK)->exists($voucher->path);
    }

    public function absolutePath(PaymentVoucher $voucher): string
    {
        return Storage::disk(self::DISK)->path($voucher->path);
    }
}
