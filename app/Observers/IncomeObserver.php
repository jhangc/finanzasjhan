<?php

namespace App\Observers;

use App\Models\Income;
use App\Services\Accounts\AccountBalanceAdjuster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Un ingreso recibido en una cuenta sube su saldo; borrarlo lo revierte.
 * Los ingresos solo esperados (no confirmados) no mueven saldos.
 */
class IncomeObserver
{
    public function __construct(private AccountBalanceAdjuster $accounts) {}

    public function created(Income $income): void
    {
        if ($income->confirmed) {
            $this->accounts->adjust($income->account_id, Money::of($income->amount));
        }
    }

    public function updated(Income $income): void
    {
        if (! $income->wasChanged(['amount', 'account_id', 'confirmed'])) {
            return;
        }

        DB::transaction(function () use ($income) {
            if ($income->getOriginal('confirmed')) {
                $this->accounts->adjust($income->getOriginal('account_id'), Money::sub('0', $income->getOriginal('amount')));
            }
            if ($income->confirmed) {
                $this->accounts->adjust($income->account_id, Money::of($income->amount));
            }
        });
    }

    public function deleted(Income $income): void
    {
        if ($income->confirmed) {
            $this->accounts->adjust($income->account_id, Money::sub('0', $income->amount));
        }
    }
}
