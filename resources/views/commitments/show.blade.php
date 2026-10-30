@extends('layouts.app')

@section('title', $commitment->name)

@php
    $types = ['debt' => 'Deuda o préstamo', 'fixed' => 'Pago mensual', 'annual' => 'Pago anual'];
@endphp

@section('content')
    <div x-data="{ editing: @js($errors->any()), paying: null }" class="space-y-4">
        <div>
            <a href="{{ route('commitments.index') }}" class="text-sm text-slate-500">← Gastos fijos</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $commitment->name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $types[$commitment->type] }}{{ $commitment->is_card ? ' · Tarjeta de crédito' : '' }}{{ $commitment->active ? '' : ' · Inactivo' }}
            </p>
        </div>

        @if ($progress)
            @include('commitments._progress', ['p' => $progress])
        @endif

        <x-card class="grid grid-cols-2 gap-4 text-sm">
            @if ($commitment->isDebt())
                <div>
                    <p class="text-xs text-slate-500">Saldo pendiente</p>
                    <x-money :amount="$commitment->total_balance" class="text-2xl font-bold" />
                </div>
            @endif
            <div>
                <p class="text-xs text-slate-500">{{ $progress ? 'Pago mínimo al mes' : 'Monto mensual' }}</p>
                <x-money :amount="$commitment->monthly_amount" class="text-2xl font-bold" />
            </div>
            <div>
                <p class="text-xs text-slate-500">Día de pago</p>
                <p class="font-medium">{{ $commitment->pay_day ?? '—' }}</p>
            </div>
            @if ($commitment->is_card)
                <div>
                    <p class="text-xs text-slate-500">Día de corte</p>
                    <p class="font-medium">{{ $commitment->cut_day }}</p>
                </div>
            @endif
            @if ($commitment->isDebt())
                <div>
                    <p class="text-xs text-slate-500">{{ $progress ? 'Cuotas restantes' : 'Meses restantes' }}</p>
                    <p class="font-medium">{{ $progress ? $progress['months_left'].' de '.$progress['term'] : ($estimate['months'] ?? '—') }}</p>
                </div>
                @if ($commitment->interest_rate !== null)
                    <div>
                        <p class="text-xs text-slate-500">Con interés (TEA {{ rtrim(rtrim($commitment->interest_rate, '0'), '.') }}%)</p>
                        <p class="font-medium">{{ $estimate['months_with_interest'] !== null ? $estimate['months_with_interest'].' meses' : 'La cuota no cubre el interés' }}</p>
                    </div>
                @endif
            @endif
        </x-card>

        @if ($statement)
            <x-card class="space-y-4 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Estado de cuenta</h2>
                        <p class="muted">Corte del {{ $statement['cut_date']->format('d/m/Y') }} · pagar hasta el {{ $statement['due_date']->format('d/m/Y') }}</p>
                    </div>
                    @if ($statement['outdated'])
                        <span class="badge bg-amber-100 text-amber-800">Desactualizado</span>
                    @elseif ($statement['paid'])
                        <span class="badge bg-emerald-100 text-emerald-700">Pagado</span>
                    @else
                        <x-due-badge :days="$statement['days_until']" />
                    @endif
                </div>

                @if ($statement['outdated'])
                    <p class="rounded-xl bg-amber-50 p-3 text-amber-800 ring-1 ring-amber-200">
                        Ya pasó un nuevo corte. Pulsa <b>Editar</b> y escribe el <b>total a pagar</b> de tu nuevo estado de cuenta.
                    </p>
                @endif

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Facturado al corte</p>
                        <x-money :amount="$statement['statement']" class="text-lg font-bold" />
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Ya pagaste</p>
                        <x-money :amount="$statement['paid_since']" class="text-lg font-bold text-emerald-700" />
                    </div>
                    <div @class(['rounded-xl p-3 ring-1', 'bg-amber-50 ring-amber-200' => ! $statement['paid'], 'bg-emerald-50 ring-emerald-200' => $statement['paid']])>
                        <p class="text-xs text-slate-600">Te falta pagar</p>
                        <x-money :amount="$statement['pending']" class="text-lg font-bold" />
                        @if (\App\Support\Money::cmp($statement['minimum_pending'], '0') > 0)
                            <p class="text-xs text-slate-500">Mínimo <x-money :amount="$statement['minimum_pending']" /></p>
                        @endif
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Consumos nuevos</p>
                        <x-money :amount="$statement['new_charges']" class="text-lg font-bold" />
                        <p class="text-xs text-slate-500">Van al próximo estado de cuenta</p>
                    </div>
                </div>
            </x-card>
        @elseif ($cycle)
            <x-card class="space-y-3 text-sm">
                <h2 class="font-semibold">Ciclo de la tarjeta</h2>
                <div class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-200">
                    <p class="text-xs text-amber-800">Para no generar intereses paga antes del {{ $cycle->paymentDueDate->format('d/m/Y') }}</p>
                    <x-money :amount="$cycle->amountToAvoidInterest" class="text-2xl font-bold text-amber-900" />
                    <p class="text-xs text-amber-800">Consumo del {{ $cycle->closedStart->format('d/m') }} al {{ $cycle->closedEnd->format('d/m') }}</p>
                </div>
                <div class="flex justify-between">
                    <span>Ciclo actual ({{ $cycle->currentStart->format('d/m') }} – {{ $cycle->currentEnd->format('d/m') }})</span>
                    <x-money :amount="$cycle->currentSpent" class="font-semibold" />
                </div>
            </x-card>
        @endif

        <div class="flex gap-2">
            @if ($commitment->active)
                <button type="button" class="btn-primary flex-1" @click="paying = @js(['id' => $commitment->id, 'name' => $commitment->name, 'is_card' => $commitment->is_card, 'amount' => $statement && ! $statement['outdated'] && ! $statement['paid'] ? $statement['pending'] : \App\Support\Money::of($commitment->monthly_amount)])">Registrar pago</button>
            @endif
            <button type="button" class="btn-secondary flex-1" @click="editing = !editing">Editar</button>
        </div>

        <x-card x-cloak x-show="editing">
            <form method="POST" action="{{ route('commitments.update', $commitment) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @include('commitments._form', ['commitment' => $commitment])
                <button type="submit" class="btn-primary w-full">Guardar cambios</button>
            </form>
            <form method="POST" action="{{ route('commitments.destroy', $commitment) }}" class="mt-3"
                  onsubmit="return confirm('¿Eliminar este gasto fijo y su historial de pagos?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger w-full">Eliminar gasto fijo</button>
            </form>
        </x-card>

        <x-card>
            <h2 class="mb-2 font-semibold">Historial de pagos</h2>
            <div class="divide-y divide-slate-100">
            @forelse ($payments as $payment)
                <div x-data="{ editing: false }" class="py-3">
                    <div class="flex items-center gap-3 text-sm">
                        @if ($payment->vouchers->isNotEmpty())
                            @php($first = $payment->vouchers->first())
                            <a href="{{ route('vouchers.show', $first) }}" title="Ver vouchers"
                               @click.prevent="$dispatch('open-vouchers', { items: @js($payment->voucherGallery()), index: 0 })"
                               class="relative shrink-0 overflow-hidden rounded-lg ring-1 ring-slate-200 hover:ring-emerald-400">
                                @if ($first->isPdf())
                                    <span class="flex h-11 w-11 items-center justify-center bg-rose-50 text-[11px] font-bold text-rose-600">PDF</span>
                                @else
                                    <img src="{{ route('vouchers.show', $first) }}" alt="Voucher" class="h-11 w-11 object-cover" loading="lazy">
                                @endif
                                @if ($payment->vouchers->count() > 1)
                                    <span class="absolute bottom-0 right-0 rounded-tl-md bg-ink-900/80 px-1 text-[10px] font-bold text-white">+{{ $payment->vouchers->count() - 1 }}</span>
                                @endif
                            </a>
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-[10px] text-slate-400 ring-1 ring-slate-200">Sin<br>voucher</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $payment->paid_at->format('d/m/Y') }}</p>
                            @php($from = $payment->account?->name ?? $payment->paidWith?->name)
                            @if ($payment->note || $from)
                                <p class="truncate text-xs text-slate-500">{{ collect([$from ? 'Desde '.$from : null, $payment->note])->filter()->join(' · ') }}</p>
                            @endif
                            @if ($payment->vouchers->isNotEmpty())
                                <button type="button" class="text-xs font-medium text-emerald-700 hover:underline"
                                        @click="$dispatch('open-vouchers', { items: @js($payment->voucherGallery()), index: 0 })">
                                    Ver {{ $payment->vouchers->count() === 1 ? 'voucher' : $payment->vouchers->count().' vouchers' }}
                                </button>
                            @endif
                        </div>
                        <x-money :amount="$payment->amount" class="font-semibold" />
                        <button type="button" class="text-xs font-medium text-slate-500 underline hover:text-slate-900" @click="editing = !editing"
                                x-text="editing ? 'Cerrar' : 'Editar'"></button>
                    </div>

                    <div x-cloak x-show="editing" x-transition class="mt-3 space-y-3 rounded-2xl bg-slate-50/80 p-4 ring-1 ring-slate-200/70">
                        <form method="POST" action="{{ route('commitment-payments.update', $payment) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="commitment_id" value="{{ $payment->commitment_id }}">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <x-field label="Monto (S/)">
                                    <input name="amount" type="text" inputmode="decimal" class="input bg-white" value="{{ $payment->amount }}" required>
                                </x-field>
                                <x-field label="Fecha de pago">
                                    <input name="paid_at" type="date" class="input bg-white" value="{{ $payment->paid_at->toDateString() }}" required>
                                </x-field>
                                <x-field label="Nota">
                                    <input name="note" type="text" class="input bg-white" value="{{ $payment->note }}" maxlength="255">
                                </x-field>
                            </div>
                            <x-payment-source :payment="$payment" :cards-disabled="$commitment->is_card ? 'true' : 'false'" />
                            <x-voucher-input :payment="$payment" />
                            <button type="submit" class="btn-primary w-full">Guardar cambios</button>
                        </form>
                        <form method="POST" action="{{ route('commitment-payments.destroy', $payment) }}" onsubmit="return confirm('¿Eliminar este pago? La deuda volverá a subir.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger w-full">Eliminar pago</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="py-2 text-sm text-slate-500">Sin pagos registrados.</p>
            @endforelse
            </div>
        </x-card>

        @include('commitments._pay-modal')
    </div>
@endsection
