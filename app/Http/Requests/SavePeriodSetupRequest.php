<?php

namespace App\Http\Requests;

class SavePeriodSetupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'expected_income' => $this->amountOrZero(),
            'savings_target' => $this->amountOrZero(),
            'envelopes' => ['array', 'max:50'],
            'envelopes.*.id' => ['nullable', 'integer'],
            'envelopes.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'envelopes.*.allocated' => $this->amountOrZero(),
            'envelopes.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
