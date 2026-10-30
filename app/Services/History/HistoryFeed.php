<?php

namespace App\Services\History;

use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Lista unificada del mes: gastos, ingresos, movimientos entre cuentas y pagos.
 */
class HistoryFeed
{
    public const TYPES = ['movement' => 'Gastos', 'income' => 'Ingresos', 'transfer' => 'Movimientos', 'payment' => 'Pagos'];

    /**
     * @return Collection<int, array>
     */
    public function for(Period $period, ?string $type = null): Collection
    {
        $items = collect();

        if ($type === null || $type === 'movement') {
            $items = $items->concat(
                Movement::whereIn('envelope_id', $period->envelopes()->select('id'))
                    ->with(['envelope:id,name,color', 'paidWith:id,name', 'account:id,name'])
                    ->get()
                    ->map(fn (Movement $m) => [
                        'type' => 'movement',
                        'id' => $m->id,
                        'date' => $m->spent_at,
                        'title' => $m->envelope->name,
                        'subtitle' => collect([$m->note, $this->from($m->account?->name, $m->paidWith?->name)])->filter()->join(' · '),
                        'amount' => $m->amount,
                        'sign' => '-',
                        'model' => $m,
                    ])
            );
        }

        // Los movimientos entre cuentas se guardan como ingresos con origen.
        if (in_array($type, [null, 'income', 'transfer'], true)) {
            $incomes = $period->incomes()->with(['account:id,name', 'fromAccount:id,name', 'fromCard:id,name']);
            match ($type) {
                'income' => $incomes->earned(),
                'transfer' => $incomes->transfers(),
                default => null,
            };

            $items = $items->concat(
                $incomes->get()->map(fn (Income $i) => [
                    'type' => 'income',
                    'id' => $i->id,
                    'date' => $i->received_at,
                    'title' => $i->source,
                    'subtitle' => collect([
                        $i->confirmed ? 'Recibido' : 'Esperado',
                        ($i->fromAccount ?? $i->fromCard) ? 'desde '.($i->fromAccount ?? $i->fromCard)->name : null,
                        $i->account ? 'en '.$i->account->name : null,
                    ])->filter()->join(' · '),
                    'amount' => $i->amount,
                    'currency' => $i->currency,
                    'sign' => $i->isTransfer() ? '⇄ ' : '+',
                    'model' => $i,
                ])
            );
        }

        if ($type === null || $type === 'payment') {
            $items = $items->concat(
                $period->commitmentPayments()->with(['commitment:id,name', 'vouchers', 'account:id,name', 'paidWith:id,name'])->get()->map(fn (CommitmentPayment $p) => [
                    'type' => 'payment',
                    'id' => $p->id,
                    'date' => $p->paid_at,
                    'title' => 'Pago: '.$p->commitment->name,
                    'subtitle' => collect([$p->note, $this->from($p->account?->name, $p->paidWith?->name)])->filter()->join(' · '),
                    'amount' => $p->amount,
                    'sign' => '-',
                    'model' => $p,
                ])
            );
        }

        return $items->sortByDesc(fn ($item) => $item['date']->timestamp)->values();
    }

    /** "desde BCP Sueldo" o "con BCP Visa". */
    private function from(?string $account, ?string $card): ?string
    {
        return $account ? 'desde '.$account : ($card ? 'con '.$card : null);
    }
}
