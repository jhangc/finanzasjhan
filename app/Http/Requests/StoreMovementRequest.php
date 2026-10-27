<?php

namespace App\Http\Requests;

class StoreMovementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'envelope_id' => ['required', 'integer', $this->owned('envelopes')],
            'amount' => $this->money(),
            'spent_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'paid_with_commitment_id' => ['nullable', 'integer', $this->ownedCard()],
        ];
    }
}
