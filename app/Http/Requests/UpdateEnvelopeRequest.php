<?php

namespace App\Http\Requests;

class UpdateEnvelopeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'allocated' => $this->amountOrZero(),
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
