<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Único punto que modifica el saldo de una cuenta de débito.
 */
class AccountBalanceAdjuster
{
    /** Suma $delta (negativo = sale dinero) al saldo, con bloqueo de fila. */
    public function adjust(?int $accountId, string $delta): void
    {
        if ($accountId === null || Money::isZero($delta)) {
            return;
        }

        DB::transaction(function () use ($accountId, $delta) {
            $account = Account::query()->lockForUpdate()->find($accountId);

            if ($account === null) {
                return;
            }

            $account->balance = Money::add($account->balance, $delta);
            $account->saveQuietly();
        });
    }
}
