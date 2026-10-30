@extends('layouts.app')

@section('title', 'Historial')

@section('content')
    <div class="flex items-center justify-between gap-2">
        <h1 class="text-2xl font-bold tracking-tight">Historial</h1>
        @if ($periods->isNotEmpty())
            <form method="GET" action="{{ route('history.index') }}">
                @if ($type)
                    <input type="hidden" name="type" value="{{ $type }}">
                @endif
                <select name="period" class="input py-1.5 text-sm" onchange="this.form.submit()" aria-label="Mes">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @selected($period?->id === $p->id)>
                            {{ ucfirst(\Carbon\Carbon::create($p->year, $p->month, 1)->locale('es')->translatedFormat('F Y')) }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if ($period)
        <div class="flex gap-2 overflow-x-auto">
            @foreach (['' => 'Todo'] + \App\Services\History\HistoryFeed::TYPES as $key => $label)
                <a href="{{ route('history.index', array_filter(['period' => $period->id, 'type' => $key ?: null])) }}"
                   @class(['badge px-3 py-1.5 text-sm', 'bg-slate-900 text-white' => $type === ($key ?: null), 'bg-white text-slate-700 ring-1 ring-slate-200' => $type !== ($key ?: null)])>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endif

    <x-card class="divide-y divide-slate-100 p-0">
        @forelse ($items as $item)
            @php($model = $item['model'])
            <div x-data="{ editing: false }" class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $item['title'] }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $item['date']->format($item['type'] === 'movement' ? 'd/m H:i' : 'd/m') }}{{ $item['subtitle'] ? ' · '.$item['subtitle'] : '' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($item['type'] === 'payment' && $model->vouchers->isNotEmpty())
                            <a href="{{ route('vouchers.show', $model->vouchers->first()) }}"
                               @click.prevent="$dispatch('open-vouchers', { items: @js($model->voucherGallery()), index: 0 })" class="badge bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">
                                {{ $model->vouchers->count() === 1 ? 'Voucher' : $model->vouchers->count().' vouchers' }}
                            </a>
                        @endif
                        <span @class(['font-semibold tabular-nums', 'text-emerald-700' => $item['sign'] === '+'])>
                            {{ $item['sign'] }}{{ \App\Support\Money::format($item['amount'], $item['currency'] ?? 'PEN') }}
                        </span>
                        <button type="button" class="text-xs text-slate-500 underline" @click="editing = !editing">Editar</button>
                    </div>
                </div>

                <div x-cloak x-show="editing" class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
                    @if ($item['type'] === 'movement')
                        <form method="POST" action="{{ route('movements.update', $model) }}" class="grid grid-cols-2 gap-2">
                            @csrf
                            @method('PUT')
                            <x-field label="Monto">
                                <input name="amount" type="text" inputmode="decimal" class="input" value="{{ $model->amount }}" required>
                            </x-field>
                            <x-field label="Categoría">
                                <select name="envelope_id" class="input">
                                    @foreach ($envelopes as $envelope)
                                        <option value="{{ $envelope->id }}" @selected($envelope->id === $model->envelope_id)>{{ $envelope->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field label="Fecha y hora">
                                <input name="spent_at" type="datetime-local" class="input" value="{{ $model->spent_at->format('Y-m-d\TH:i') }}" required>
                            </x-field>
                            <x-field label="Pagado con">
                                <select name="paid_with_commitment_id" class="input">
                                    <option value="">Efectivo / débito</option>
                                    @foreach ($cards as $card)
                                        <option value="{{ $card->id }}" @selected($card->id === $model->paid_with_commitment_id)>{{ $card->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field label="Nota" class="col-span-2">
                                <input name="note" type="text" class="input" value="{{ $model->note }}" maxlength="255">
                            </x-field>
                            <button type="submit" class="btn-primary col-span-2">Guardar</button>
                        </form>
                        <form method="POST" action="{{ route('movements.destroy', $model) }}" onsubmit="return confirm('¿Eliminar este gasto?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger w-full">Eliminar</button>
                        </form>
                    @elseif ($item['type'] === 'income')
                        <form method="POST" action="{{ route('incomes.update', $model) }}" class="grid grid-cols-2 gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="period_id" value="{{ $model->period_id }}">
                            <x-field label="Monto">
                                <input name="amount" type="text" inputmode="decimal" class="input" value="{{ $model->amount }}" required>
                            </x-field>
                            <x-field label="Moneda">
                                <select name="currency" class="input">
                                    @foreach (['PEN', 'USD'] as $currency)
                                        <option @selected($model->currency === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field label="Fuente">
                                <input name="source" type="text" class="input" value="{{ $model->source }}" required maxlength="100">
                            </x-field>
                            <x-field label="Fecha">
                                <input name="received_at" type="date" class="input" value="{{ $model->received_at->toDateString() }}" required>
                            </x-field>
                            <label class="col-span-2 flex items-center gap-2 text-sm">
                                <input type="checkbox" name="confirmed" value="1" class="rounded border-slate-300" @checked($model->confirmed)> Confirmado
                            </label>
                            <button type="submit" class="btn-primary col-span-2">Guardar</button>
                        </form>
                        <form method="POST" action="{{ route('incomes.destroy', $model) }}" onsubmit="return confirm('¿Eliminar este ingreso?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger w-full">Eliminar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('commitment-payments.update', $model) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2">
                            @csrf
                            @method('PUT')
                            <x-field label="Monto">
                                <input name="amount" type="text" inputmode="decimal" class="input" value="{{ $model->amount }}" required>
                            </x-field>
                            <x-field label="Gasto fijo">
                                <select name="commitment_id" class="input">
                                    @foreach ($commitments as $commitment)
                                        <option value="{{ $commitment->id }}" @selected($commitment->id === $model->commitment_id)>{{ $commitment->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field label="Fecha de pago">
                                <input name="paid_at" type="date" class="input" value="{{ $model->paid_at->toDateString() }}" required>
                            </x-field>
                            <x-field label="Nota">
                                <input name="note" type="text" class="input" value="{{ $model->note }}" maxlength="255">
                            </x-field>
                            <x-payment-source :payment="$model" class="col-span-2" />
                            <x-voucher-input :payment="$model" class="col-span-2" />
                            <button type="submit" class="btn-primary col-span-2">Guardar</button>
                        </form>
                        <form method="POST" action="{{ route('commitment-payments.destroy', $model) }}" onsubmit="return confirm('¿Eliminar este pago? El saldo se revertirá.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger w-full">Eliminar</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-4 text-center text-sm text-slate-500">
                {{ $period ? 'No hay registros este mes.' : 'Aún no has armado ningún mes.' }}
            </p>
        @endforelse
    </x-card>
@endsection
