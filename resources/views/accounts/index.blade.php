@extends('layouts.app')

@section('title', 'Cuentas')

@use('App\Support\Money')

@php
    $o = $overview;
    $palette = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899'];
@endphp

@section('content')
<div x-data="{ addingAccount: @js($errors->any() && old('_form') === 'account'), addingCard: @js($errors->any() && old('_form') === 'card'), addingLoan: @js($errors->any() && old('_form') === 'loan') }" class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Cuentas</h1>
            <p class="muted">Lo que tienes en tus cuentas y lo que debes en tarjetas y préstamos.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" @click="addingLoan = true">+ Préstamo</button>
            <button type="button" class="btn-secondary" @click="addingCard = true">+ Tarjeta de crédito</button>
            <button type="button" class="btn-primary" @click="addingAccount = true">+ Cuenta de débito</button>
        </div>
    </div>

    {{-- Resumen --}}
    <section class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3 rounded-2xl bg-ink-900 p-5 text-white shadow-soft sm:px-6">
        <div class="flex items-center gap-4">
            <span class="rounded-xl bg-white/10 p-2.5 text-emerald-400"><x-icon name="piggy" class="h-5 w-5" /></span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tienes − lo que debes</p>
                <x-money :amount="$o['net_all']" :class="'block text-3xl font-extrabold tracking-tight'.(Money::isNegative($o['net_all']) ? ' text-rose-300' : '')" />
            </div>
        </div>
        <div class="text-sm text-slate-400 sm:text-right">
            <p>Cuentas − tarjetas − préstamos <span class="text-slate-500">(sin el valor de tus bienes)</span></p>
            <p class="mt-1 text-slate-300">Solo tarjetas: <x-money :amount="$o['net']" class="font-semibold" /></p>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-card>
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tienes en cuentas</p>
                <span class="rounded-xl bg-emerald-50 p-2 text-emerald-600"><x-icon name="wallet" class="h-4 w-4" /></span>
            </div>
            <x-money :amount="$o['debit_total']" class="mt-3 block whitespace-nowrap text-2xl font-bold tracking-tight tabular-nums" />
            <p class="mt-1 text-xs text-slate-500">
                {{ $o['accounts']->count() }} {{ $o['accounts']->count() === 1 ? 'cuenta' : 'cuentas' }}
                @if (Money::cmp($o['usd_without_rate'], '0') > 0)
                    · más <x-money :amount="$o['usd_without_rate']" currency="USD" /> (<a href="{{ route('settings') }}" class="underline">pon tu tipo de cambio</a>)
                @endif
            </p>
        </x-card>
        <x-card>
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Debes en tarjetas</p>
                <span class="rounded-xl bg-rose-50 p-2 text-rose-600"><x-icon name="card" class="h-4 w-4" /></span>
            </div>
            <x-money :amount="$o['card_debt']" class="mt-3 block whitespace-nowrap text-2xl font-bold tracking-tight tabular-nums text-rose-600" />
            <p class="mt-1 text-xs text-slate-500">
                @if (Money::cmp($o['card_to_pay'], '0') > 0)
                    A pagar este mes: <b class="text-slate-700"><x-money :amount="$o['card_to_pay']" /></b>
                @else
                    {{ $o['cards']->count() }} {{ $o['cards']->count() === 1 ? 'tarjeta' : 'tarjetas' }}
                @endif
            </p>
        </x-card>
        <x-card>
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Debes en préstamos</p>
                <span class="rounded-xl bg-orange-50 p-2 text-orange-600"><x-icon name="banknotes" class="h-4 w-4" /></span>
            </div>
            <x-money :amount="$o['loan_debt']" class="mt-3 block whitespace-nowrap text-2xl font-bold tracking-tight tabular-nums text-orange-600" />
            <p class="mt-1 text-xs text-slate-500">
                {{ $o['loans']->count() }} {{ $o['loans']->count() === 1 ? 'préstamo' : 'préstamos' }}
                @if (Money::cmp($o['loan_monthly'], '0') > 0) · cuotas de <x-money :amount="$o['loan_monthly']" /> al mes @endif
            </p>
        </x-card>
        <x-card>
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Crédito disponible</p>
                <span class="rounded-xl bg-sky-50 p-2 text-sky-600"><x-icon name="bolt" class="h-4 w-4" /></span>
            </div>
            <x-money :amount="$o['credit_available']" class="mt-3 block whitespace-nowrap text-2xl font-bold tracking-tight tabular-nums" />
            <p class="mt-1 text-xs text-slate-500">de <x-money :amount="$o['credit_limit']" /> de línea total</p>
            @if ($o['credit_usage'] !== null)
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div @class(['h-full rounded-full', 'bg-emerald-500' => $o['credit_usage'] < 30, 'bg-amber-500' => $o['credit_usage'] >= 30 && $o['credit_usage'] < 70, 'bg-rose-600' => $o['credit_usage'] >= 70])
                         style="width: {{ $o['credit_usage'] }}%"></div>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">Usas el {{ $o['credit_usage'] }}% de tu línea</p>
            @endif
        </x-card>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-2">
        {{-- Débito --}}
        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Cuentas de débito</h2>
                    <p class="muted">Ahorros, sueldo y efectivo. Actualiza el saldo cuando quieras.</p>
                </div>
                <x-money :amount="$o['debit_total']" class="font-bold" />
            </div>

            <div class="space-y-3">
                @forelse ($o['accounts'] as $i => $account)
                    <div x-data="{ editing: false }" class="rounded-2xl bg-slate-50/70 p-4 ring-1 ring-slate-200/70">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white"
                                  style="background: {{ $account->color ?: $palette[$i % count($palette)] }}">
                                {{ mb_strtoupper(mb_substr($account->bank ?: $account->name, 0, 1)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $account->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ collect([$account->bank, $account->typeLabel(), $account->currency === 'USD' ? 'Dólares' : null])->filter()->join(' · ') }}</p>
                            </div>
                            <x-money :amount="$account->balance" :currency="$account->currency" class="text-lg font-bold" />
                            <button type="button" class="text-xs font-medium text-slate-500 underline hover:text-slate-900" @click="editing = !editing" x-text="editing ? 'Cerrar' : 'Editar'"></button>
                        </div>

                        <div x-cloak x-show="editing" x-transition class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                            <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                @include('accounts._form', ['account' => $account])
                                <button type="submit" class="btn-primary w-full">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('¿Eliminar la cuenta «{{ $account->name }}»?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger w-full">Eliminar cuenta</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center">
                        <p class="text-sm text-slate-500">Aún no registras cuentas de débito.</p>
                        <button type="button" class="btn-primary mt-3" @click="addingAccount = true">+ Agregar cuenta</button>
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- Crédito --}}
        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Tarjetas de crédito</h2>
                    <p class="muted">Cuánto debes, tu línea y lo que te queda disponible.</p>
                </div>
                <x-money :amount="$o['card_debt']" class="font-bold text-rose-600" />
            </div>

            <div class="space-y-3">
                @forelse ($o['cards'] as $item)
                    @php($card = $item['commitment'])
                    <a href="{{ route('commitments.show', $card) }}" class="block rounded-2xl bg-gradient-to-br from-ink-900 to-ink-700 p-4 text-white shadow-soft transition hover:-translate-y-0.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $card->name }}</p>
                                <p class="text-xs text-slate-400">
                                    Corte día {{ $card->cut_day ?? '—' }} · pago día {{ $card->pay_day ?? '—' }}{{ $card->active ? '' : ' · inactiva' }}
                                </p>
                            </div>
                            @if ($item['over_limit'])
                                <span class="badge bg-rose-500 text-white">Sobre la línea</span>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-400">Debes</p>
                                <x-money :amount="$item['debt']" class="font-bold text-rose-300" />
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-400">Línea</p>
                                @if ($item['limit'] !== null)
                                    <x-money :amount="$item['limit']" class="font-semibold" />
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-400">Disponible</p>
                                @if ($item['available'] !== null)
                                    <x-money :amount="$item['available']" class="font-semibold text-emerald-300" />
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </div>
                        </div>

                        @php($st = $item['statement'])
                        @if ($st && ! $st['outdated'])
                            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                <div @class(['rounded-xl p-2.5', 'bg-emerald-500/15' => $st['paid'], 'bg-amber-400/15' => ! $st['paid']])>
                                    @if ($st['paid'])
                                        <p class="text-[11px] uppercase tracking-wide text-emerald-300">Estado de cuenta pagado</p>
                                        <x-money :amount="$st['statement']" class="font-bold text-emerald-200" />
                                    @else
                                        <p class="text-[11px] uppercase tracking-wide text-amber-200">A pagar hasta el {{ $st['due_date']->format('d/m') }}</p>
                                        <x-money :amount="$st['pending']" class="font-bold text-amber-100" />
                                        @if (Money::cmp($st['minimum_pending'], '0') > 0)
                                            <p class="text-[11px] text-amber-200/80">Mínimo <x-money :amount="$st['minimum_pending']" /></p>
                                        @endif
                                    @endif
                                </div>
                                <div class="rounded-xl bg-white/5 p-2.5">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Consumos nuevos</p>
                                    <x-money :amount="$st['new_charges']" class="font-bold" />
                                    <p class="text-[11px] text-slate-400">Se cobran el próximo mes</p>
                                </div>
                            </div>
                        @elseif ($st && $st['outdated'])
                            <p class="mt-4 rounded-xl bg-amber-400/15 p-2.5 text-xs text-amber-100">
                                Ya pasó un nuevo corte. Actualiza el <b>total a pagar</b> con tu nuevo estado de cuenta (Editar).
                            </p>
                        @else
                            <p class="mt-4 rounded-xl bg-white/5 p-2.5 text-xs text-slate-300">
                                Agrega el <b>total a pagar</b> de tu estado de cuenta (Editar) para separar lo que pagas este mes de los consumos nuevos.
                            </p>
                        @endif

                        @if ($item['usage'] !== null)
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div @class(['h-full rounded-full', 'bg-emerald-400' => $item['usage'] < 30, 'bg-amber-400' => $item['usage'] >= 30 && $item['usage'] < 70, 'bg-rose-400' => $item['usage'] >= 70])
                                     style="width: {{ $item['usage'] }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">Usas el {{ $item['usage'] }}% de la línea</p>
                        @else
                            <p class="mt-3 text-[11px] text-slate-400">Agrega su línea de crédito en Editar para ver cuánto te queda disponible.</p>
                        @endif
                    </a>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center">
                        <p class="text-sm text-slate-500">Aún no registras tarjetas de crédito.</p>
                        <button type="button" class="btn-primary mt-3" @click="addingCard = true">+ Agregar tarjeta</button>
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- Préstamos --}}
    <x-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold">Préstamos</h2>
                <p class="muted">Yape, préstamos personales, del banco y deudas en cuotas.</p>
            </div>
            <div class="flex items-center gap-3">
                <x-money :amount="$o['loan_debt']" class="font-bold text-orange-600" />
                <button type="button" class="btn-secondary py-1.5" @click="addingLoan = true">+ Préstamo</button>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($o['loans'] as $item)
                @php($loan = $item['commitment'])
                @php($p = $item['progress'])
                <a href="{{ route('commitments.show', $loan) }}" @class([
                    'block rounded-2xl p-4 ring-1 transition hover:-translate-y-0.5 hover:shadow-soft',
                    'bg-emerald-50/60 ring-emerald-200' => $item['finished'],
                    'bg-slate-50/70 ring-slate-200/70' => ! $item['finished'],
                    'opacity-60' => ! $loan->active,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $loan->name }}</p>
                            <p class="text-xs text-slate-500">
                                @if (\App\Support\Money::cmp($item['monthly'], '0') > 0)
                                    Cuota <x-money :amount="$p ? $p['installment'] : $item['monthly']" />
                                    @if ($p && $p['frequency'] !== 'monthly') ({{ $p['frequency'] === 'weekly' ? 'semanal' : 'quincenal' }}) @endif
                                @endif
                                @if ($loan->pay_day && (! $p || $p['frequency'] === 'monthly')) · día {{ $loan->pay_day }} @endif
                                @if (! $loan->active) · inactivo @endif
                            </p>
                        </div>
                        @if ($item['finished'])
                            <span class="badge bg-emerald-100 text-emerald-700">Pagado</span>
                        @elseif ($item['days_until'] !== null && $loan->active)
                            <x-due-badge :days="$item['days_until']" />
                        @endif
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-2">
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Te falta</p>
                            <x-money :amount="$item['remaining']" class="text-xl font-bold text-orange-600" />
                        </div>
                        @if ($item['next_due'])
                            <p class="text-right text-xs text-slate-500">Próximo pago<br><b class="text-slate-700">{{ $item['next_due']->format('d/m/Y') }}</b></p>
                        @endif
                    </div>

                    @if ($p)
                        <div class="mt-3">
                            <div class="mb-1 flex justify-between text-[11px] text-slate-500">
                                <span>Pagado <x-money :amount="$p['paid']" /> de <x-money :amount="$p['original']" /></span>
                                <span class="font-semibold">{{ $p['percent'] }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $p['percent'] }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                @if ($p['finished'])
                                    ¡Terminaste!
                                @elseif ($p['installments_due'] === 0)
                                    1.ª cuota el {{ $p['first_due']?->format('d/m/Y') }} · {{ $p['term'] }} cuotas
                                @else
                                    Cuota {{ $p['current_installment'] }} de {{ $p['term'] }}
                                @endif
                            </p>
                        </div>
                    @endif
                </a>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center md:col-span-2 2xl:col-span-3">
                    <p class="text-sm text-slate-500">Aún no registras préstamos.</p>
                    <button type="button" class="btn-primary mt-3" @click="addingLoan = true">+ Agregar préstamo</button>
                </div>
            @endforelse
        </div>
    </x-card>

    <x-modal show="addingLoan" title="Nuevo préstamo">
        <form method="POST" action="{{ route('commitments.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="from" value="accounts">
            <input type="hidden" name="_form" value="loan">
            @include('commitments._form', ['defaultKind' => 'debt', 'lockKind' => true])
            <button type="submit" class="btn-primary w-full">Agregar préstamo</button>
        </form>
    </x-modal>

    <x-modal show="addingAccount" title="Nueva cuenta de débito" size="max-w-lg">
        <form method="POST" action="{{ route('accounts.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_form" value="account">
            @include('accounts._form', ['account' => null])
            <button type="submit" class="btn-primary w-full">Agregar cuenta</button>
        </form>
    </x-modal>

    <x-modal show="addingCard" title="Nueva tarjeta de crédito">
        <form method="POST" action="{{ route('commitments.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="from" value="accounts">
            <input type="hidden" name="_form" value="card">
            @include('commitments._form', ['defaultKind' => 'card', 'lockKind' => true])
            <button type="submit" class="btn-primary w-full">Agregar tarjeta</button>
        </form>
    </x-modal>
</div>
    @include('accounts._banks')
@endsection
