<?php

namespace App\Services\Debts;

use App\Models\Commitment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Único punto que modifica total_balance de un compromiso.
 */
class BalanceAdjuster
{
    /** Suma $delta (puede ser negativo) al saldo, con bloqueo de fila. */
    public function adjust(?int $commitmentId, string $delta): void
    {
        if ($commitmentId === null || Money::isZero($delta)) {
            return;
        }

        DB::transaction(function () use ($commitmentId, $delta) {
            $commitment = Commitment::query()->lockForUpdate()->find($commitmentId);

            if ($commitment === null) {
                return;
            }

            $commitment->total_balance = Money::add($commitment->total_balance, $delta);
            $commitment->saveQuietly();
        });
    }
}
