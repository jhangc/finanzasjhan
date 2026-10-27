<?php

namespace App\Observers;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Services\Accounts\AccountBalanceAdjuster;
use App\Services\Debts\BalanceAdjuster;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Pagar una deuda baja su saldo. El dinero sale de una cuenta (baja su saldo)
 * o se carga a una tarjeta (sube su deuda). Borrar el pago lo revierte todo.
 */
class CommitmentPaymentObserver
{
    public function __construct(
        private BalanceAdjuster $balances,
        private AccountBalanceAdjuster $accounts,
    ) {}

    public function created(CommitmentPayment $payment): void
    {
        $this->apply($payment->commitment_id, $payment->account_id, $payment->paid_with_commitment_id, Money::of($payment->amount));
    }

    public function updated(CommitmentPayment $payment): void
    {
        if (! $payment->wasChanged(['amount', 'commitment_id', 'account_id', 'paid_with_commitment_id']) || $payment->trashed()) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $this->apply(
                $payment->getOriginal('commitment_id'),
                $payment->getOriginal('account_id'),
                $payment->getOriginal('paid_with_commitment_id'),
                Money::sub('0', $payment->getOriginal('amount')),
            );
            $this->apply($payment->commitment_id, $payment->account_id, $payment->paid_with_commitment_id, Money::of($payment->amount));
        });
    }

    public function deleted(CommitmentPayment $payment): void
    {
        if ($payment->isForceDeleting() && $payment->getOriginal('deleted_at') !== null) {
            return;
        }

        $this->apply($payment->commitment_id, $payment->account_id, $payment->paid_with_commitment_id, Money::sub('0', $payment->amount));
    }

    public function restored(CommitmentPayment $payment): void
    {
        $this->apply($payment->commitment_id, $payment->account_id, $payment->paid_with_commitment_id, Money::of($payment->amount));
    }

    /** $amount positivo = pago: baja la deuda pagada, baja la cuenta origen o sube la tarjeta usada. */
    private function apply(?int $commitmentId, ?int $accountId, ?int $cardId, string $amount): void
    {
        if ($commitmentId !== null) {
            $isDebt = Commitment::whereKey($commitmentId)->where('type', Commitment::TYPE_DEBT)->exists();
            if ($isDebt) {
                $this->balances->adjust($commitmentId, Money::sub('0', $amount));
            }
        }

        $this->accounts->adjust($accountId, Money::sub('0', $amount));
        $this->balances->adjust($cardId, $amount);
    }
}
