<?php

namespace App\Http\Requests;

class UpdateSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currency_default' => ['required', 'in:PEN,USD'],
            'usd_rate' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999.999'],
            'default_card_id' => ['nullable', 'integer', $this->ownedCard()],
            'default_account_id' => ['nullable', 'integer', $this->owned('accounts')],
            'reminder_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
