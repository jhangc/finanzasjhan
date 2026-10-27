<?php

namespace App\Http\Requests;

use App\Models\Commitment;
use App\Services\Debts\CardStatement;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Throwable;

class StoreCommitmentRequest extends BaseRequest
{
    /** Si el usuario escribió "lo que te falta pagar" (en vez de dejar que se calcule). */
    public bool $balanceWasTyped = false;

    public function rules(): array
    {
        $type = $this->input('type');
        $isCard = $this->boolean('is_card');
        $inInstallments = $type === 'debt' && ! $isCard && filled($this->input('term_months'));

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:debt,fixed,annual'],
            'total_balance' => [...$this->amountOrZero(required: false), 'required_if:type,debt'],
            'credit_limit' => [...$this->amountOrZero(required: false), 'required_if_accepted:is_card'],
            'statement_balance' => [...$this->amountOrZero(required: false), 'lte:total_balance'],
            'statement_date' => ['nullable', 'date'],

            // Préstamo en cuotas: número de cuotas, monto por cuota, periodo y fecha de la 1.ª cuota.
            'term_months' => ['nullable', 'integer', 'between:1,600'],
            'installment_amount' => $inInstallments ? $this->money() : ['nullable'],
            'frequency' => ['required', 'in:'.implode(',', array_keys(Commitment::FREQUENCIES))],
            'start_date' => ['nullable', 'date', 'required_with:term_months'],
            'original_amount' => $this->amountOrZero(required: false),
            'down_payment' => [...$this->amountOrZero(required: false), 'lte:original_amount'],
            'down_payment_date' => ['nullable', 'date'],

            // Tarjetas y préstamos sin cuotas fijas pueden no tener monto mensual (0 = sin registrar).
            'monthly_amount' => $isCard || $type === 'debt' ? $this->amountOrZero() : $this->money(),
            'pay_day' => ['nullable', 'integer', 'between:1,31', $type === 'fixed' || $isCard ? 'required' : 'nullable'],
            'cut_day' => ['nullable', 'integer', 'between:1,31', 'required_if_accepted:is_card'],
            'interest_rate' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999.999'],
            'is_card' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'monthly_amount' => $this->input('type') === 'annual' ? 'total del año' : 'monto mensual',
            'total_balance' => 'lo que te falta pagar',
            'original_amount' => 'monto total de la deuda',
            'credit_limit' => 'línea de crédito',
            'statement_balance' => 'total a pagar del estado de cuenta',
            'down_payment' => 'cuota inicial',
            'down_payment_date' => 'fecha de la inicial',
            'term_months' => 'número de cuotas',
            'installment_amount' => 'monto por cuota',
            'frequency' => 'periodo',
            'start_date' => 'fecha de la 1.ª cuota',
        ] + parent::attributes();
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        $isDebt = $type === 'debt';
        $isCard = $isDebt && $this->boolean('is_card');
        $isLoan = $isDebt && ! $isCard;
        $inInstallments = $isLoan && filled($this->input('term_months'));

        $this->merge([
            'is_card' => $isCard,
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'total_balance' => $isDebt ? $this->input('total_balance') : null,
            'cut_day' => $isCard ? $this->input('cut_day') : null,
            'credit_limit' => $isCard ? $this->input('credit_limit') : null,
            'statement_balance' => $isCard ? $this->input('statement_balance') : null,
            'monthly_amount' => ($isCard || $isDebt) && blank($this->input('monthly_amount')) ? '0' : $this->input('monthly_amount'),
            'frequency' => $inInstallments ? ($this->input('frequency') ?: 'monthly') : 'monthly',
        ]);

        // El estado de cuenta que se escribe corresponde al último corte.
        $cutDay = (int) $this->input('cut_day');
        $this->merge([
            'statement_date' => $isCard && filled($this->input('statement_balance')) && $cutDay >= 1 && $cutDay <= 31
                ? CardStatement::lastCut($cutDay)->toDateString()
                : null,
            'term_months' => $inInstallments ? $this->input('term_months') : null,
            'start_date' => $inInstallments ? $this->input('start_date') : null,
            'original_amount' => $inInstallments ? $this->input('original_amount') : null,
            'down_payment' => $inInstallments ? $this->input('down_payment') : null,
            'down_payment_date' => $inInstallments ? $this->input('down_payment_date') : null,
            'installment_amount' => $inInstallments ? $this->input('installment_amount', $this->input('monthly_amount')) : null,
        ]);

        if ($inInstallments) {
            $this->prepareInstallments();
        }

        // Deuda nueva: si no se indicó cuánto falta, falta todo (la inicial se descuenta al registrarla como pago).
        $this->balanceWasTyped = filled($this->input('total_balance'));
        if ($inInstallments && ! $this->balanceWasTyped && filled($this->input('original_amount'))) {
            $this->merge(['total_balance' => $this->input('original_amount')]);
        }

        // Pago anual: se escribe el total del año y se guarda la parte mensual (÷ 12).
        $annual = str_replace(',', '.', (string) $this->input('annual_total'));
        if ($type === 'annual' && is_numeric($annual)) {
            $this->merge(['monthly_amount' => Money::cmp($annual, '0') > 0 ? Money::div($annual, '12') : $annual]);
        }
    }

    /**
     * Cuotas: el total sale de cuotas × monto (+ inicial) si no se escribió otro;
     * el monto mensual del presupuesto es el equivalente según el periodo;
     * en cuotas mensuales el día de pago es el de la 1.ª cuota.
     */
    private function prepareInstallments(): void
    {
        $installment = str_replace(',', '.', (string) $this->input('installment_amount'));
        $term = (int) $this->input('term_months');

        if (! is_numeric($installment) || $term < 1) {
            return;
        }

        if (blank($this->input('original_amount'))) {
            $this->merge(['original_amount' => Money::add(
                Money::mul($installment, (string) $term),
                $this->input('down_payment') ?: '0',
            )]);
        }

        $this->merge(['monthly_amount' => match ($this->input('frequency')) {
            'weekly' => Money::div(Money::mul($installment, '52'), '12'),
            'biweekly' => Money::mul($installment, '2'),
            default => Money::of($installment),
        }]);

        $payDay = null;
        if ($this->input('frequency') === 'monthly') {
            try {
                $payDay = filled($this->input('pay_day'))
                    ? (int) $this->input('pay_day')
                    : CarbonImmutable::parse((string) $this->input('start_date'))->day;
            } catch (Throwable) {
                $payDay = null;
            }
        }
        $this->merge(['pay_day' => $payDay]);
    }
}
