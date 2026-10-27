<?php

namespace App\Http\Requests;

class StoreEnvelopeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', $this->owned('periods')],
            'name' => ['required', 'string', 'max:60'],
            'allocated' => $this->amountOrZero(),
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
