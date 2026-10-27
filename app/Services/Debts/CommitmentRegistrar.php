<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\User;
use App\Services\Budget\PeriodBuilder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Registra un gasto fijo. Si es una deuda con cuota inicial, la inicial queda
 * guardada como el primer pago (en el mes en que se pagó).
 */
class CommitmentRegistrar
{
    public function __construct(private PeriodBuilder $periods) {}

    /**
     * @param  bool  $balanceWasTyped  Si el usuario escribió cuánto le falta: entonces la inicial
     *                                 ya está descontada y el pago se guarda sin volver a restarla.
     */
    public function create(User $user, array $data, bool $balanceWasTyped = false): Commitment
    {
        return DB::transaction(function () use ($user, $data, $balanceWasTyped) {
            $commitment = $user->commitments()->create(Arr::except($data, ['down_payment_date']));

            $down = Money::of($data['down_payment'] ?? null);
            if ($commitment->hasTerm() && Money::cmp($down, '0') > 0) {
                $paidAt = CarbonImmutable::parse($data['down_payment_date'] ?? now())->startOfDay();

                $payment = new CommitmentPayment([
                    'user_id' => $user->id,
                    'commitment_id' => $commitment->id,
                    'period_id' => $this->periods->generate($user, $paidAt->year, $paidAt->month)->id,
                    'amount' => $down,
                    'paid_at' => $paidAt->toDateString(),
                    'note' => 'Cuota inicial',
                ]);

                // El observer baja el saldo; si el usuario ya escribió lo que le falta, no hay que restar otra vez.
                $balanceWasTyped ? $payment->saveQuietly() : $payment->save();
            }

            return $commitment->fresh();
        });
    }
}
