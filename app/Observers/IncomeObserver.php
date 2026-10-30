<?php

namespace App\Observers;

use App\Models\Income;
use App\Services\Accounts\AccountBalanceAdjuster;
use App\Services\Debts\BalanceAdjuster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Un ingreso recibido en una cuenta sube su saldo; si salió de otra cuenta, baja la de origen,
 * y si salió de una tarjeta, sube su deuda. Borrarlo lo revierte.
 * Los ingresos solo esperados (no confirmados) no mueven saldos.
 */
class IncomeObserver
{
    public function __construct(
        private AccountBalanceAdjuster $accounts,
        private BalanceAdjuster $balances,
    ) {}

    public function created(Income $income): void
    {
        if ($income->confirmed) {
            $this->apply($income->account_id, $income->from_account_id, $income->from_commitment_id, Money::of($income->amount));
        }
    }

    public function updated(Income $income): void
    {
        if (! $income->wasChanged(['amount', 'account_id', 'from_account_id', 'from_commitment_id', 'confirmed'])) {
            return;
        }

        DB::transaction(function () use ($income) {
            if ($income->getOriginal('confirmed')) {
                $this->apply(
                    $income->getOriginal('account_id'),
                    $income->getOriginal('from_account_id'),
                    $income->getOriginal('from_commitment_id'),
                    Money::sub('0', $income->getOriginal('amount')),
                );
            }
            if ($income->confirmed) {
                $this->apply($income->account_id, $income->from_account_id, $income->from_commitment_id, Money::of($income->amount));
            }
        });
    }

    public function deleted(Income $income): void
    {
        if ($income->confirmed) {
            $this->apply($income->account_id, $income->from_account_id, $income->from_commitment_id, Money::sub('0', $income->amount));
        }
    }

    /** $amount positivo = entra a la cuenta destino y sale del origen (cuenta o tarjeta). */
    private function apply(?int $accountId, ?int $fromAccountId, ?int $fromCardId, string $amount): void
    {
        $this->accounts->adjust($accountId, $amount);
        $this->accounts->adjust($fromAccountId, Money::sub('0', $amount));
        $this->balances->adjust($fromCardId, $amount);
    }
}
