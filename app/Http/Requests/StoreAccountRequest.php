<?php

namespace App\Http\Requests;

use App\Models\Account;

class StoreAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'bank' => ['nullable', 'string', 'max:60'],
            'type' => ['required', 'in:'.implode(',', array_keys(Account::TYPES))],
            'currency' => ['required', 'in:PEN,USD'],
            // El saldo puede ser negativo (sobregiro).
            'balance' => ['required', 'numeric', 'decimal:0,2', 'min:-9999999999.99', 'max:9999999999.99'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['balance' => 'saldo', 'bank' => 'banco', 'currency' => 'moneda'] + parent::attributes();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->input('currency', 'PEN'),
            'balance' => str_replace(',', '', (string) $this->input('balance', '0')) ?: '0',
        ]);
    }
}
