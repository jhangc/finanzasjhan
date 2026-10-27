<?php

namespace App\Http\Requests;

class QuickMovementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'max:255'],
            // Segunda vuelta: el usuario eligió un sobre con los botones.
            'envelope_id' => ['nullable', 'integer', $this->owned('envelopes')],
            'amount' => $this->money(required: false),
            'paid_with_card' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            // De dónde sale: "account:ID", "card:ID" o "none" (ids ajenos se ignoran en el servicio).
            'source' => ['nullable', 'regex:/^(account:\d+|card:\d+|none)$/'],
        ];
    }
}
