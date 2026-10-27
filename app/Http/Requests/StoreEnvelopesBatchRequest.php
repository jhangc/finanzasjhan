<?php

namespace App\Http\Requests;

/**
 * Crear varias categorías a la vez (modal de "Anotar").
 */
class StoreEnvelopesBatchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1', 'max:30'],
            'categories.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'categories.*.allocated' => $this->amountOrZero(required: false),
            'redirect_to' => ['nullable', 'in:transactions,setup'],
        ];
    }

    public function attributes(): array
    {
        return [
            'categories' => 'categorías',
            'categories.*.name' => 'nombre de la categoría',
            'categories.*.allocated' => 'monto',
        ] + parent::attributes();
    }

    public function messages(): array
    {
        return ['categories.required' => 'Marca al menos una categoría.'];
    }
}
