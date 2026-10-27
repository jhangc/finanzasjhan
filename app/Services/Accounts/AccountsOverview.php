<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\Commitment;
use App\Models\User;
use App\Services\Debts\CardStatement;
use App\Services\Debts\DebtProgress;
use App\Services\Debts\DueDates;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Cuánto tienes (cuentas de débito) y cuánto debes en tarjetas, con su línea de crédito.
 * Los montos en USD se suman en soles con el tipo de cambio manual del usuario.
 */
class AccountsOverview
{
    public function __construct(
        private CardStatement $statements,
        private DebtProgress $progress,
    ) {}

    public function for(User $user): array
    {
        $rate = $user->usd_rate;

        $accounts = Account::ownedBy($user)->orderBy('sort_order')->orderBy('name')->get();
        $cards = Commitment::ownedBy($user)->where('is_card', true)->orderBy('name')->get()
            ->map(fn (Commitment $card) => $this->card($card));
        $loans = Commitment::ownedBy($user)->where('type', Commitment::TYPE_DEBT)->where('is_card', false)
            ->orderByDesc('active')->orderBy('name')->get()
            ->map(fn (Commitment $loan) => $this->loan($loan));

        $usdWithoutRate = $rate === null
            ? Money::sum($accounts->where('currency', 'USD')->pluck('balance'))
            : '0.00';

        $debit = Money::sum($accounts->map(fn (Account $a) => $this->inSoles($a->balance, $a->currency, $rate)));
        $cardDebt = Money::sum($cards->pluck('debt'));
        $toPayNow = Money::sum($cards->map(fn ($c) => $c['statement'] && ! $c['statement']['outdated'] ? $c['statement']['pending'] : null)->filter());
        $limit = Money::sum($cards->pluck('limit')->filter());
        $available = Money::sum($cards->pluck('available')->filter());
        $loanDebt = Money::sum($loans->pluck('remaining'));

        return [
            'accounts' => $accounts,
            'cards' => $cards,
            'loans' => $loans,
            'loan_debt' => $loanDebt,
            'loan_monthly' => Money::sum($loans->filter(fn ($l) => $l['commitment']->active && ! $l['finished'])->pluck('monthly')),
            'total_debt' => Money::add($cardDebt, $loanDebt),
            'net_all' => Money::sub(Money::sub($debit, $cardDebt), $loanDebt),
            'debit_total' => $debit,
            'usd_without_rate' => $usdWithoutRate,
            'card_debt' => $cardDebt,
            'card_to_pay' => $toPayNow,
            'credit_limit' => $limit,
            'credit_available' => $available,
            'credit_usage' => Money::cmp($limit, '0') > 0 ? min(100, Money::percent($this->debtWithLimit($cards), $limit)) : null,
            'net' => Money::sub($debit, $cardDebt),
            'rate' => $rate,
        ];
    }

    /** Deuda solo de las tarjetas que tienen línea registrada (para el % de uso). */
    private function debtWithLimit(Collection $cards): string
    {
        return Money::sum($cards->filter(fn ($c) => $c['limit'] !== null)->pluck('debt'));
    }

    /** Préstamo o deuda (no tarjeta): saldo, cuota, avance y próximo vencimiento. */
    private function loan(Commitment $loan): array
    {
        $remaining = Money::positive($loan->total_balance);
        $progress = $this->progress->for($loan);

        $nextDue = null;
        if ($progress) {
            // Con cuotas: la siguiente cuota del calendario (mensual, quincenal o semanal).
            $nextDue = $progress['next_due'];
        } elseif ($loan->pay_day !== null && Money::cmp($remaining, '0') > 0) {
            $nextDue = DueDates::next($loan->pay_day);
        }

        return [
            'commitment' => $loan,
            'remaining' => $remaining,
            'monthly' => Money::of($loan->monthly_amount),
            'progress' => $progress,
            'next_due' => $nextDue,
            'days_until' => $nextDue ? DueDates::daysUntil($nextDue) : null,
            'finished' => Money::cmp($remaining, '0') <= 0,
        ];
    }

    private function card(Commitment $card): array
    {
        $debt = Money::positive($card->total_balance);
        $limit = $card->credit_limit !== null ? Money::of($card->credit_limit) : null;

        return [
            'commitment' => $card,
            'debt' => $debt,
            'limit' => $limit,
            'available' => $limit !== null ? Money::positive(Money::sub($limit, $debt)) : null,
            'usage' => $limit !== null && Money::cmp($limit, '0') > 0 ? min(100, Money::percent($debt, $limit)) : null,
            'over_limit' => $limit !== null && Money::cmp($debt, $limit) > 0,
            'statement' => $this->statements->for($card),
        ];
    }

    private function inSoles(string $amount, string $currency, ?string $rate): string
    {
        if ($currency !== 'USD') {
            return Money::of($amount);
        }

        return $rate !== null ? Money::mul($amount, $rate) : '0.00';
    }
}
