<?php

namespace App\Http\Requests;

class StoreRecurringRuleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'kind' => ['required', 'in:envelope,commitment_due'],
            'commitment_id' => ['nullable', 'integer', 'required_if:kind,commitment_due', $this->owned('commitments')],
            'amount' => $this->money(),
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'commitment_id' => $this->input('kind') === 'commitment_due' ? $this->input('commitment_id') : null,
        ]);
    }
}
