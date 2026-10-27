@extends('layouts.app')

@section('title', 'Gastos fijos')

@php
    $types = ['debt' => 'Deuda o préstamo', 'fixed' => 'Pago mensual', 'annual' => 'Pago anual'];
@endphp

@section('content')
    <div x-data="{ creating: @js($errors->any() && old('_form') === 'create'), paying: null }" class="space-y-4">
        <div class="flex items-center justify-between">
            <div><h1 class="page-title">Gastos fijos</h1><p class="muted">Lo que pagas sí o sí cada mes: servicios, cuotas, deudas y tarjetas.</p></div>
            <button type="button" class="btn-primary" @click="creating = true">+ Nuevo</button>
        </div>

        <x-card>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div><p class="text-xs text-slate-500">Este mes</p><x-money :amount="$overview['total']" class="font-semibold" /></div>
                <div><p class="text-xs text-slate-500">Pagado</p><x-money :amount="$overview['paid']" class="font-semibold text-emerald-700" /></div>
                <div><p class="text-xs text-slate-500">Faltan</p><x-money :amount="$overview['pending']" class="font-semibold" /></div>
            </div>
        </x-card>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($overview['items'] as $item)
            @php($c = $item['commitment'])
            <x-card @class(['flex flex-col', 'opacity-60' => ! $c->active])>
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('commitments.show', $c) }}" class="min-w-0">
                        <p class="truncate font-semibold">{{ $c->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $c->is_card ? 'Tarjeta de crédito' : $types[$c->type] }}{{ $c->active ? '' : ' · Inactivo' }}
                            @if ($c->pay_day) · Día {{ $c->pay_day }} @endif
                        </p>
                    </a>
                    @if ($c->active)
                        <x-due-badge :days="$item['days_until']" :paid="$item['is_paid']" :starts-on="$item['starts_later'] ? $item['due_date'] : null" />
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div>
                        <p class="text-xs text-slate-500">Mensual</p>
                        <x-money :amount="$item['monthly']" class="font-medium" />
                    </div>
                    @if ($c->isDebt())
                        <div>
                            <p class="text-xs text-slate-500">Saldo</p>
                            <x-money :amount="$c->total_balance" class="font-medium" />
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Meses restantes</p>
                            <p class="font-medium">{{ $item['progress']['months_left'] ?? $item['months_left'] ?? '—' }}</p>
                        </div>
                    @else
                        <div class="col-span-2">
                            <p class="text-xs text-slate-500">Próximo vencimiento</p>
                            <p class="font-medium">{{ $item['due_date']?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                    @endif
                </div>

                @if ($c->active)
                    <div class="mt-3">
                        <div class="mb-1 flex justify-between text-xs text-slate-500">
                            @if ($item['starts_later'])
                                <span>Registrado este mes: cuenta desde el próximo pago</span>
                            @else
                                <span>Pagado este mes: <x-money :amount="$item['paid']" /></span>
                            @endif
                            @if ($c->isDebt() && $item['due_date'])
                                <span>Vence {{ $item['due_date']->format('d/m') }}</span>
                            @endif
                        </div>
                        <x-progress :percent="$item['percent']" :state="$item['covered'] ? 'ok' : ($item['overdue'] ? 'over' : 'warning')" />
                    </div>

                    @if ($item['progress'])
                        @include('commitments._progress', ['p' => $item['progress'], 'compact' => true])
                    @endif

                    <div class="mt-auto flex gap-2 pt-4">
                        <button type="button" class="btn-primary flex-1"
                                @click="paying = @js(['id' => $c->id, 'name' => $c->name, 'amount' => \App\Support\Money::of(\App\Support\Money::isZero($item['pending']) ? $item['monthly'] : $item['pending'])])">
                            Registrar pago
                        </button>
                        <a href="{{ route('commitments.show', $c) }}" class="btn-secondary">Detalle</a>
                    </div>
                @endif
            </x-card>
        @empty
            <x-card class="py-10 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3 2xl:col-span-4">
                Aún no tienes gastos fijos. Agrega tu alquiler, servicios, cuotas o tarjetas.
                <div class="mt-4"><button type="button" class="btn-primary" @click="creating = true">+ Agregar gasto fijo</button></div>
            </x-card>
        @endforelse
        </div>

        @include('commitments._pay-modal')

        <x-modal show="creating" title="Nuevo gasto fijo">
            <form method="POST" action="{{ route('commitments.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="create">
                @include('commitments._form')
                <button type="submit" class="btn-primary w-full">Crear</button>
            </form>
        </x-modal>
    </div>
@endsection
