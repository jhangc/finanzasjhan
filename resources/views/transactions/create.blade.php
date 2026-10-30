@extends('layouts.app')

@section('title', 'Anotar')

@php
    $allTargets = collect($targets)->flatMap(fn ($g) => $g['options'])->keyBy('value');
    $allSources = $accounts->merge($cards)->keyBy('value');
@endphp

@section('content')
<div x-data="{ creatingCategories: @js($errors->has('categories') || $errors->has('categories.*')) }">
<form method="POST" action="{{ route('transactions.store') }}" enctype="multipart/form-data"
      x-data="{
          kind: @js(old('kind', request('kind', 'expense'))),
          amount: @js(old('amount', '')),
          target: @js(old('target', request('target', ''))),
          source: @js(old('source', '')),
          incomeAccount: @js((string) old('income_account', '')),
          incomeSource: @js(old('income_source', '')),
          incomeFrom: @js(old('income_from', '')),
          targets: @js($allTargets),
          sources: @js($allSources),
          get targetOption() { return this.targets[this.target] ?? null; },
          get sourceOption() { return this.sources[this.source] ?? null; },
          get isCommitment() { return this.target.startsWith('commitment:'); },
          get payingCard() { return this.targetOption?.is_card ?? false; },
          pickIncomeAccount(id) {
              this.incomeAccount = id;
              if (this.incomeFrom === 'account:' + id) this.incomeFrom = '';
          },
          pickTarget(value) {
              this.target = value;
              const suggested = this.targets[value]?.suggested;
              if (suggested && !fc.toCents(this.amount)) this.amount = suggested;
              if (this.payingCard && this.source.startsWith('card:')) this.source = '';
          },
          balanceAfter() {
              if (!this.source.startsWith('account:')) return null;
              return fc.fromCents(fc.toCents(this.sourceOption?.balance) - fc.toCents(this.amount));
          },
      }"
      class="space-y-5">
    @csrf
    <input type="hidden" name="kind" :value="kind">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Anotar</h1>
            <p class="muted">Registra lo que pagas y con qué, o lo que recibes.</p>
        </div>
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-white p-1 text-sm font-semibold shadow-soft ring-1 ring-slate-200/70">
            <button type="button" @click="kind = 'expense'" class="rounded-lg px-5 py-2 transition"
                    :class="kind === 'expense' ? 'bg-rose-500 text-white' : 'text-slate-500 hover:text-slate-900'">Gasto o pago</button>
            <button type="button" @click="kind = 'income'" class="rounded-lg px-5 py-2 transition"
                    :class="kind === 'income' ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:text-slate-900'">Ingreso</button>
        </div>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- Monto y fecha --}}
            <x-card>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="label" for="amount">Monto</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-2xl font-bold text-slate-400">S/</span>
                            <input id="amount" name="amount" x-model="amount" type="text" inputmode="decimal" required autofocus
                                   class="input py-4 pl-14 text-3xl font-extrabold tracking-tight tabular-nums" placeholder="0.00">
                        </div>
                    </div>
                    <x-field label="Fecha" name="date">
                        <input id="date" name="date" type="date" class="input py-4" value="{{ old('date', now()->toDateString()) }}" required>
                    </x-field>
                </div>
            </x-card>

            {{-- GASTO: ¿Qué estás pagando? --}}
            <x-card x-show="kind === 'expense'">
                <h2 class="text-lg font-semibold">1. ¿Qué estás pagando?</h2>
                <input type="hidden" name="target" :value="kind === 'expense' ? target : ''">

                <div class="mt-4 space-y-5">
                    @foreach ($targets as $group)
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group['title'] }}</p>
                                @if ($group['key'] === 'categories')
                                    <button type="button" @click="creatingCategories = true" class="text-xs font-semibold text-emerald-700 hover:underline">+ Nueva categoría</button>
                                @endif
                            </div>
                            @if ($group['options']->isEmpty())
                                <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">
                                    @if ($group['key'] === 'categories')
                                        Aún no tienes categorías este mes. <button type="button" @click="creatingCategories = true" class="font-semibold text-emerald-700 underline">Créalas aquí</button>.
                                    @elseif ($group['key'] === 'cards')
                                        Sin tarjetas. <a href="{{ route('accounts.index') }}" class="font-semibold text-emerald-700 underline">Agrégalas en Cuentas</a>.
                                    @else
                                        Nada registrado.
                                    @endif
                                </p>
                            @else
                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($group['options'] as $option)
                                        <button type="button" @click="pickTarget(@js($option['value']))"
                                                class="flex items-center gap-3 rounded-xl p-3 text-left transition"
                                                :class="target === @js($option['value']) ? 'bg-rose-50 ring-2 ring-rose-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                            <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $option['color'] ?? ($group['key'] === 'cards' ? '#f59e0b' : ($group['key'] === 'loans' ? '#f97316' : '#94a3b8')) }}"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                                <span @class(['block truncate text-xs', 'text-rose-600' => $option['over'], 'text-slate-500' => ! $option['over']])>{{ $option['hint'] }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- GASTO: ¿Con qué pagas? --}}
            <x-card x-show="kind === 'expense'">
                <h2 class="text-lg font-semibold">2. ¿Con qué pagas?</h2>
                <input type="hidden" name="source" :value="kind === 'expense' ? source : ''">

                <div class="mt-4 space-y-5">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Cuentas y efectivo</p>
                        @if ($accounts->isEmpty())
                            <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">Sin cuentas. <a href="{{ route('accounts.index') }}" class="font-semibold text-emerald-700 underline">Agrega tus cuentas (y Efectivo)</a> para que el saldo baje solo.</p>
                        @else
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($accounts as $option)
                                    <button type="button" @click="source = @js($option['value'])"
                                            class="flex items-center gap-3 rounded-xl p-3 text-left transition"
                                            :class="source === @js($option['value']) ? 'bg-emerald-50 ring-2 ring-emerald-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-icon name="wallet" class="h-4 w-4" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                            <span class="block truncate text-xs text-slate-500">{{ $option['hint'] }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($cards->isNotEmpty())
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Tarjetas de crédito</p>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($cards as $option)
                                    <button type="button" @click="if (!payingCard) source = @js($option['value'])" :disabled="payingCard"
                                            class="flex items-center gap-3 rounded-xl p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-40"
                                            :class="source === @js($option['value']) ? 'bg-emerald-50 ring-2 ring-emerald-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><x-icon name="card" class="h-4 w-4" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                            <span class="block truncate text-xs text-slate-500">{{ $option['hint'] }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <p x-show="payingCard" class="mt-2 text-xs text-slate-500">Una tarjeta se paga desde una cuenta.</p>
                        </div>
                    @endif

                    <button type="button" @click="source = 'none'" class="w-full rounded-xl p-3 text-left text-sm transition"
                            :class="source === 'none' ? 'bg-slate-100 ring-2 ring-slate-400' : 'ring-1 ring-dashed ring-slate-300 text-slate-500 hover:bg-slate-50'">
                        Otro / no descontar de ninguna cuenta
                    </button>
                </div>
            </x-card>

            {{-- INGRESO --}}
            <x-card x-show="kind === 'income'" x-cloak>
                <h2 class="text-lg font-semibold">¿De dónde viene y a qué cuenta entra?</h2>
                <div class="mt-4 space-y-4">
                    <x-field label="De dónde viene" name="income_source">
                        <input name="income_source" x-model="incomeSource" type="text" class="input" maxlength="100" placeholder="Sueldo, freelance, venta…">
                        <div class="mt-2 flex flex-wrap items-center gap-1.5"
                             x-data="{
                                 list: @js($incomeSources),
                                 adding: false,
                                 draft: '',
                                 async send(method, name) {
                                     const res = await fetch(@js(route('income-sources.store')), {
                                         method,
                                         headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                         body: JSON.stringify({ name }),
                                     });
                                     if (res.ok) this.list = (await res.json()).sources;
                                 },
                                 async add() {
                                     const name = this.draft.trim();
                                     if (!name) return;
                                     await this.send('POST', name);
                                     incomeSource = name;
                                     this.draft = '';
                                     this.adding = false;
                                 },
                             }">
                            <template x-for="name in list" :key="name">
                                <span class="group inline-flex items-center rounded-full text-xs font-medium transition"
                                      :class="incomeSource === name ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-emerald-100 hover:text-emerald-800'">
                                    <button type="button" class="py-1 pl-2.5 pr-1" @click="incomeSource = name" x-text="name"></button>
                                    <button type="button" class="pr-2 opacity-40 hover:opacity-100" title="Quitar"
                                            @click="if (confirm('¿Quitar “' + name + '” de tus categorías?')) send('DELETE', name)">×</button>
                                </span>
                            </template>
                            <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => $refs.newSource.focus())"
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-dashed ring-emerald-300 hover:bg-emerald-50">+ Categoría</button>
                            <span x-show="adding" x-cloak class="inline-flex items-center gap-1">
                                <input x-ref="newSource" x-model="draft" type="text" maxlength="100" placeholder="Nueva categoría"
                                       class="input w-40 py-1 text-xs" @keydown.enter.prevent="add()" @keydown.escape="adding = false; draft = ''">
                                <button type="button" @click="add()" class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500">Agregar</button>
                            </span>
                        </div>
                    </x-field>
                    <div>
                        <span class="label">¿A qué cuenta entra?</span>
                        <input type="hidden" name="income_account" :value="kind === 'income' ? incomeAccount : ''">
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($accounts as $option)
                                <button type="button" @click="pickIncomeAccount(@js((string) $option['id']))"
                                        class="flex items-center gap-3 rounded-xl p-3 text-left transition"
                                        :class="incomeAccount === @js((string) $option['id']) ? 'bg-emerald-50 ring-2 ring-emerald-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-icon name="wallet" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $option['hint'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                            <button type="button" @click="incomeAccount = ''" class="rounded-xl p-3 text-left text-sm transition"
                                    :class="incomeAccount === '' ? 'bg-slate-100 ring-2 ring-slate-400' : 'ring-1 ring-slate-200 text-slate-500 hover:bg-slate-50'">
                                Ninguna / en mano
                            </button>
                        </div>
                    </div>
                    <div>
                        <span class="label">¿Sale de otra cuenta o tarjeta? <span class="font-normal normal-case text-slate-400">(opcional)</span></span>
                        <p class="mb-2 text-xs text-slate-500">Si pasas plata de tu tarjeta o de otra cuenta tuya, elige de dónde sale: se registra como <strong>movimiento</strong>, se descuenta de ahí (o sube la deuda de la tarjeta) y no cuenta como ingreso del mes.</p>
                        <input type="hidden" name="income_from" :value="kind === 'income' ? incomeFrom : ''">
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <button type="button" @click="incomeFrom = ''" class="rounded-xl p-3 text-left text-sm transition"
                                    :class="incomeFrom === '' ? 'bg-slate-100 ring-2 ring-slate-400' : 'ring-1 ring-slate-200 text-slate-500 hover:bg-slate-50'">
                                De fuera (no sale de mis cuentas)
                            </button>
                            @foreach ($accounts as $option)
                                <button type="button" @click="incomeFrom = @js($option['value'])" :disabled="incomeAccount === @js((string) $option['id'])"
                                        class="flex items-center gap-3 rounded-xl p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-40"
                                        :class="incomeFrom === @js($option['value']) ? 'bg-rose-50 ring-2 ring-rose-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-icon name="wallet" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $option['hint'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                            @foreach ($cards as $option)
                                <button type="button" @click="incomeFrom = @js($option['value'])"
                                        class="flex items-center gap-3 rounded-xl p-3 text-left transition"
                                        :class="incomeFrom === @js($option['value']) ? 'bg-rose-50 ring-2 ring-rose-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><x-icon name="card" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold">{{ $option['name'] }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $option['hint'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                        @error('income_from')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-field label="Nota (opcional)" name="note">
                    <input id="note" name="note" type="text" class="input" value="{{ old('note') }}" maxlength="255" placeholder="Ej. menú del día, N.º de operación…">
                </x-field>
                <div class="mt-4" x-show="kind === 'expense' && isCommitment" x-cloak>
                    <x-voucher-input />
                </div>
            </x-card>
        </div>

        {{-- Resumen --}}
        <aside class="space-y-4 lg:sticky lg:top-8">
            <section class="rounded-3xl bg-ink-900 p-6 text-white shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400" x-text="kind === 'expense' ? 'Vas a registrar' : (incomeFrom ? 'Vas a mover' : 'Vas a recibir')"></p>
                <p class="mt-2 text-4xl font-extrabold tracking-tight tabular-nums"
                   :class="kind === 'expense' ? 'text-rose-300' : 'text-emerald-300'"
                   x-text="(kind === 'expense' ? '− ' : (incomeFrom ? '⇄ ' : '+ ')) + fc.money(amount || '0')"></p>

                <dl class="mt-5 space-y-3 text-sm" x-show="kind === 'expense'">
                    <div>
                        <dt class="text-xs text-slate-400">Qué pagas</dt>
                        <dd class="font-semibold" x-text="targetOption?.name ?? 'Elige qué estás pagando'" :class="!targetOption && 'text-slate-500'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Con qué pagas</dt>
                        <dd class="font-semibold" x-text="source === 'none' ? 'Sin descontar de cuentas' : (sourceOption?.name ?? 'Elige con qué pagas')" :class="!source && 'text-slate-500'"></dd>
                    </div>
                    <template x-if="balanceAfter() !== null">
                        <div class="rounded-xl bg-white/5 p-3">
                            <dt class="text-xs text-slate-400">Saldo de <span x-text="sourceOption.name"></span> después</dt>
                            <dd class="text-lg font-bold" :class="fc.toCents(balanceAfter()) < 0 ? 'text-rose-300' : 'text-white'" x-text="fc.money(balanceAfter())"></dd>
                        </div>
                    </template>
                    <template x-if="source.startsWith('card:')">
                        <p class="rounded-xl bg-amber-400/15 p-3 text-xs text-amber-100">Se suma a la deuda de la tarjeta.</p>
                    </template>
                </dl>

                <dl class="mt-5 space-y-3 text-sm" x-show="kind === 'income'" x-cloak>
                    <div>
                        <dt class="text-xs text-slate-400">De</dt>
                        <dd class="font-semibold" x-text="incomeSource || '—'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Entra a</dt>
                        <dd class="font-semibold" x-text="sources['account:' + incomeAccount]?.name ?? 'Ninguna cuenta'"></dd>
                    </div>
                    <template x-if="incomeFrom">
                        <div>
                            <dt class="text-xs text-slate-400">Sale de</dt>
                            <dd class="font-semibold text-rose-300" x-text="sources[incomeFrom]?.name"></dd>
                        </div>
                    </template>
                    <template x-if="incomeFrom.startsWith('card:')">
                        <p class="rounded-xl bg-amber-400/15 p-3 text-xs text-amber-100">Se suma a la deuda de la tarjeta.</p>
                    </template>
                    <template x-if="incomeFrom">
                        <p class="rounded-xl bg-white/5 p-3 text-xs text-slate-300">Es un movimiento entre tus cuentas: no suma como ingreso del mes.</p>
                    </template>
                </dl>
            </section>

            <button type="submit" class="btn w-full py-3.5 text-base text-white"
                    :class="kind === 'expense' ? 'bg-rose-500 hover:bg-rose-400' : 'bg-emerald-600 hover:bg-emerald-500'"
                    :disabled="!fc.toCents(amount) || (kind === 'expense' && (!target || !source)) || (kind === 'income' && !incomeFrom && !incomeSource.trim())"
                    x-text="kind === 'expense' ? 'Registrar gasto' : (incomeFrom ? 'Registrar movimiento' : 'Registrar ingreso')"></button>
        </aside>
    </div>
</form>

@include('transactions._categories-modal')
</div>
@endsection
