<?php

namespace App\Observers;

use App\Models\Movement;
use App\Services\Accounts\AccountBalanceAdjuster;
use App\Services\Debts\BalanceAdjuster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Un gasto pagado con tarjeta sube el saldo de la tarjeta; pagado desde una cuenta, baja su saldo.
 * Borrarlo lo revierte.
 */
class MovementObserver
{
    public function __construct(
        private BalanceAdjuster $balances,
        private AccountBalanceAdjuster $accounts,
    ) {}

    public function created(Movement $movement): void
    {
        $this->apply($movement->paid_with_commitment_id, $movement->account_id, Money::of($movement->amount));
    }

    public function updated(Movement $movement): void
    {
        if (! $movement->wasChanged(['amount', 'paid_with_commitment_id', 'account_id']) || $movement->trashed()) {
            return;
        }

        DB::transaction(function () use ($movement) {
            $this->apply(
                $movement->getOriginal('paid_with_commitment_id'),
                $movement->getOriginal('account_id'),
                Money::sub('0', $movement->getOriginal('amount')),
            );
            $this->apply($movement->paid_with_commitment_id, $movement->account_id, Money::of($movement->amount));
        });
    }

    public function deleted(Movement $movement): void
    {
        // Un forceDelete de algo ya borrado no debe revertir dos veces.
        if ($movement->isForceDeleting() && $movement->getOriginal('deleted_at') !== null) {
            return;
        }

        $this->apply($movement->paid_with_commitment_id, $movement->account_id, Money::sub('0', $movement->amount));
    }

    public function restored(Movement $movement): void
    {
        $this->apply($movement->paid_with_commitment_id, $movement->account_id, Money::of($movement->amount));
    }

    /** $amount positivo = gasto: sube la deuda de la tarjeta o baja el saldo de la cuenta. */
    private function apply(?int $cardId, ?int $accountId, string $amount): void
    {
        $this->balances->adjust($cardId, $amount);
        $this->accounts->adjust($accountId, Money::sub('0', $amount));
    }
}
