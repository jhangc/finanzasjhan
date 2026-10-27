<?php

namespace App\Http\Requests;

use App\Support\Money;

class UpdateCommitmentRequest extends StoreCommitmentRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Al editar, dejar vacío "lo que te falta" conserva el saldo actual (no lo reinicia al total).
        $commitment = $this->route('commitment');
        if (! $this->balanceWasTyped && $this->input('type') === 'debt' && $commitment?->total_balance !== null) {
            $this->merge(['total_balance' => $commitment->total_balance]);
        }

        // Si el estado de cuenta no cambió, conserva su fecha de corte (no lo "renueva" al editar otra cosa).
        $statement = $this->input('statement_balance');
        if ($commitment?->statement_date && filled($statement) && is_numeric($statement)
            && Money::cmp($statement, $commitment->statement_balance) === 0) {
            $this->merge(['statement_date' => $commitment->statement_date->toDateString()]);
        }
    }
}
