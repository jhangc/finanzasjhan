<?php

namespace App\Http\Requests;

class StoreIncomeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', $this->owned('periods')],
            'amount' => $this->money(),
            'currency' => ['required', 'in:PEN,USD'],
            'source' => ['required', 'string', 'max:100'],
            'received_at' => ['required', 'date'],
            'confirmed' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->input('currency', 'PEN'),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }
}
