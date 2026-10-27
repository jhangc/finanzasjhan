<?php

namespace App\Services\Movements;

use App\Http\Requests\RegisterTransactionRequest;
use App\Models\Income;
use App\Models\Movement;
use App\Models\User;
use App\Services\Budget\PeriodBuilder;
use App\Services\Debts\VoucherStorage;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registra lo que se anota en la pantalla "Anotar":
 * - gasto de una categoría → movimiento (baja la cuenta o sube la tarjeta),
 * - pago de un gasto fijo, préstamo o tarjeta → pago (baja la deuda y la cuenta, o sube la tarjeta usada),
 * - ingreso → ingreso recibido (sube la cuenta).
 * Los saldos los mueven los observers.
 */
class TransactionRecorder
{
    public function __construct(
        private PeriodBuilder $periods,
        private VoucherStorage $vouchers,
    ) {}

    /** @return string Mensaje para el usuario. */
    public function record(User $user, RegisterTransactionRequest $request): string
    {
        $data = $request->validated();
        $amount = Money::of($data['amount']);
        $date = CarbonImmutable::parse($data['date']);
        $note = $data['note'] ?? null;

        return DB::transaction(function () use ($user, $request, $data, $amount, $date, $note) {
            if ($data['kind'] === 'income') {
                return $this->income($user, $request, $amount, $date);
            }

            $from = $request->account?->name ?? $request->card?->name;
            $suffix = $from ? " desde {$from}" : '';

            if ($request->envelope) {
                // Si es hoy, guarda la hora actual; si no, mediodía de ese día.
                $spentAt = $date->isToday() ? now() : $date->setTime(12, 0);

                Movement::create([
                    'user_id' => $user->id,
                    'envelope_id' => $request->envelope->id,
                    'amount' => $amount,
                    'spent_at' => $spentAt,
                    'note' => $note,
                    'account_id' => $request->account?->id,
                    'paid_with_commitment_id' => $request->card?->id,
                ]);

                return 'Gasto de '.Money::format($amount)." en {$request->envelope->name}{$suffix}.";
            }

            $payment = $user->commitmentPayments()->create([
                'commitment_id' => $request->commitment->id,
                'period_id' => $this->periods->generate($user, $date->year, $date->month)->id,
                'amount' => $amount,
                'paid_at' => $date->toDateString(),
                'note' => $note,
                'account_id' => $request->account?->id,
                'paid_with_commitment_id' => $request->card?->id,
            ]);
            $this->vouchers->add($payment, $request->voucherFiles());

            return 'Pago de '.Money::format($amount)." a {$request->commitment->name}{$suffix}.";
        });
    }

    private function income(User $user, RegisterTransactionRequest $request, string $amount, CarbonImmutable $date): string
    {
        $account = $request->filled('income_account') ? $user->accounts()->find($request->integer('income_account')) : null;

        Income::create([
            'user_id' => $user->id,
            'period_id' => $this->periods->generate($user, $date->year, $date->month)->id,
            'amount' => $amount,
            'currency' => $account?->currency ?? 'PEN',
            'source' => $request->input('income_source'),
            'received_at' => $date->toDateString(),
            'confirmed' => true,
            'account_id' => $account?->id,
        ]);

        return 'Ingreso de '.Money::format($amount, $account?->currency ?? 'PEN').($account ? " en {$account->name}" : '').'.';
    }
}
