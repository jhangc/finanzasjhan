<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Commitment;
use App\Services\Debts\VoucherStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * source (opcional): "account:ID" | "card:ID" | "none" — de dónde sale el dinero del pago.
 * Si no se envía al editar, se mantiene el origen que tenía.
 */
class StoreCommitmentPaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'commitment_id' => ['required', 'integer', $this->owned('commitments')],
            'amount' => $this->money(),
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'regex:/^(account:\d+|card:\d+|none)$/'],
            'vouchers' => ['nullable', 'array', 'max:'.VoucherStorage::MAX_PER_PAYMENT],
            'vouchers.*' => VoucherStorage::FILE_RULES,
            'remove_vouchers' => ['nullable', 'array'],
            'remove_vouchers.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return ['source' => 'desde dónde pagas'] + parent::attributes();
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

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('source') || $this->input('source') === 'none') {
                return;
            }

            [$type, $id] = explode(':', $this->input('source'));

            if ($type === 'account') {
                if (! Account::ownedBy($this->user())->whereKey($id)->exists()) {
                    $validator->errors()->add('source', 'Esa cuenta no existe.');
                }

                return;
            }

            if (! Commitment::ownedBy($this->user())->where('is_card', true)->whereKey($id)->exists()) {
                $validator->errors()->add('source', 'Esa tarjeta no existe.');
            } elseif (Commitment::whereKey($this->integer('commitment_id'))->value('is_card')) {
                $validator->errors()->add('source', 'Una tarjeta se paga desde una cuenta, no con otra tarjeta.');
            }
        }];
    }

    /** Datos del pago, sin los archivos; "source" se traduce a cuenta o tarjeta usada. */
    public function paymentData(): array
    {
        $data = collect($this->validated())->except(['vouchers', 'remove_vouchers', 'source'])->all();

        if ($this->filled('source')) {
            [$type, $id] = array_pad(explode(':', $this->input('source')), 2, null);
            $data['account_id'] = $type === 'account' ? (int) $id : null;
            $data['paid_with_commitment_id'] = $type === 'card' ? (int) $id : null;
        }

        return $data;
    }

    /** @return array<UploadedFile> */
    public function voucherFiles(): array
    {
        return array_values(array_filter((array) $this->file('vouchers', [])));
    }
}
