<?php

namespace App\Http\Requests;

use App\Services\Debts\VoucherStorage;
use Illuminate\Http\UploadedFile;

class StoreCommitmentPaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'commitment_id' => ['required', 'integer', $this->owned('commitments')],
            'amount' => $this->money(),
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'vouchers' => ['nullable', 'array', 'max:'.VoucherStorage::MAX_PER_PAYMENT],
            'vouchers.*' => VoucherStorage::FILE_RULES,
            'remove_vouchers' => ['nullable', 'array'],
            'remove_vouchers.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'vouchers.max' => 'Puedes subir hasta '.VoucherStorage::MAX_PER_PAYMENT.' vouchers por pago.',
            'vouchers.*.mimes' => 'Cada voucher debe ser una foto (JPG, PNG, WEBP, HEIC) o un PDF.',
            'vouchers.*.max' => 'Cada voucher puede pesar hasta 5 MB.',
            'vouchers.*.file' => 'No se pudo subir uno de los vouchers.',
        ];
    }

    /** Datos del pago, sin los archivos. */
    public function paymentData(): array
    {
        return collect($this->validated())->except(['vouchers', 'remove_vouchers'])->all();
    }

    /** @return array<UploadedFile> */
    public function voucherFiles(): array
    {
        return array_values(array_filter((array) $this->file('vouchers', [])));
    }
}
