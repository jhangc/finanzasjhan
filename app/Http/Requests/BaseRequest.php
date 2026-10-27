<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Monto de una transacción: S/ 0.01 a S/ 999,999.99, máximo 2 decimales. */
    protected function money(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'];
    }

    /** Monto que puede ser cero (asignaciones, metas, saldos). */
    protected function amountOrZero(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'];
    }

    /** El id debe existir y pertenecer al usuario autenticado. */
    protected function owned(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('user_id', $this->user()->id);
    }

    /** Una tarjeta del usuario. */
    protected function ownedCard(): Exists
    {
        return $this->owned('commitments')->where('is_card', true);
    }

    public function attributes(): array
    {
        return [
            'amount' => 'monto',
            'allocated' => 'asignado',
            'expected_income' => 'ingreso esperado',
            'savings_target' => 'ahorro',
            'envelope_id' => 'categoría',
            'commitment_id' => 'gasto fijo',
            'period_id' => 'periodo',
            'paid_with_commitment_id' => 'tarjeta',
            'default_card_id' => 'tarjeta por defecto',
            'monthly_amount' => 'monto mensual',
            'total_balance' => 'saldo',
            'pay_day' => 'día de pago',
            'cut_day' => 'día de corte',
            'interest_rate' => 'tasa',
            'usd_rate' => 'tipo de cambio',
            'reminder_email' => 'correo de recordatorios',
            'spent_at' => 'fecha',
            'paid_at' => 'fecha de pago',
            'received_at' => 'fecha',
            'day_of_month' => 'día del mes',
            'name' => 'nombre',
            'source' => 'fuente',
            'note' => 'nota',
            'input' => 'texto',
            'file' => 'archivo',
            'envelopes.*.name' => 'nombre de la categoría',
            'envelopes.*.allocated' => 'asignado',
        ];
    }
}
