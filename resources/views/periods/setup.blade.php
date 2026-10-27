@extends('layouts.app')

@section('title', 'Planificar')

@php
    $monthName = ucfirst($month->locale('es')->translatedFormat('F Y'));
    $kindLabels = ['debt' => 'Deuda o préstamo', 'fixed' => 'Pago mensual', 'annual' => 'Pago anual'];
    $addingFixed = $commitments->isEmpty() || ($errors->any() && old('from') === 'setup');
@endphp

@section('content')
<div x-data="setupPage(@js($form), @js(\App\Support\Money::of($commitmentsTotal)), @js($confirmedIncome), @js($commitments->count()))" class="space-y-5">

    {{-- Encabezado y pasos --}}
    <div>
        <h1 class="page-title">Planifica {{ $monthName }}</h1>
        <p class="muted">
            @if ($period)
                Ajusta tu plan cuando quieras. Los cambios se reflejan al instante en "Hoy".
            @else
                Tres pasos y listo: primero lo que pagas sí o sí, luego cuánto ganas y al final en qué quieres gastar.
            @endif
        </p>
    </div>

    <ol class="grid gap-2 sm:grid-cols-3">
        @foreach ([1 => ['Gastos fijos', 'Lo que pagas sí o sí'], 2 => ['Ingreso y ahorro', 'Cuánto entra y cuánto guardas'], 3 => ['Categorías', 'Tu presupuesto del día a día']] as $n => [$title, $subtitle])
            <li class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-soft ring-1 ring-slate-200/70">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                      :class="stepDone({{ $n }}) ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500'">
                    <span x-show="!stepDone({{ $n }})">{{ $n }}</span>
                    <svg x-show="stepDone({{ $n }})" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold">{{ $title }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $subtitle }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    <div class="grid items-start gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">

            {{-- Paso 1: gastos fijos --}}
            <x-card x-data="{ adding: @js($addingFixed) }">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Paso 1</p>
                        <h2 class="text-lg font-semibold">Tus gastos fijos</h2>
                        <p class="muted">Alquiler, luz, internet, cuotas, tarjetas… lo que pagas todos los meses.</p>
                    </div>
                    <button type="button" class="btn-secondary shrink-0" x-show="!adding" @click="adding = true">+ Agregar</button>
                </div>

                @if ($commitments->isNotEmpty())
                    <ul class="mt-4 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200/70">
                        @foreach ($commitments as $commitment)
                            <li class="flex items-center gap-3 px-3 py-2.5">
                                <span @class([
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                    'bg-amber-50 text-amber-600' => $commitment->is_card,
                                    'bg-rose-50 text-rose-600' => $commitment->isDebt() && ! $commitment->is_card,
                                    'bg-sky-50 text-sky-600' => ! $commitment->isDebt(),
                                ])>
                                    <x-icon :name="$commitment->is_card ? 'card' : ($commitment->isDebt() ? 'banknotes' : 'calendar')" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('commitments.show', $commitment) }}" class="block truncate text-sm font-semibold hover:underline">{{ $commitment->name }}</a>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $commitment->is_card ? 'Tarjeta de crédito' : $kindLabels[$commitment->type] }}
                                        @if ($commitment->pay_day) · día {{ $commitment->pay_day }} @endif
                                    </p>
                                </div>
                                <x-money :amount="$commitment->monthly_amount" class="text-sm font-semibold" />
                                <form method="POST" action="{{ route('commitments.destroy', $commitment) }}" onsubmit="return confirm('¿Quitar «{{ $commitment->name }}» de tus gastos fijos?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="from" value="setup">
                                    <button class="rounded-lg p-1 text-lg leading-none text-slate-300 hover:bg-rose-50 hover:text-rose-600" aria-label="Quitar">&times;</button>
                                </form>
                            </li>
                        @endforeach
                        <li class="flex justify-between bg-slate-50/80 px-3 py-2.5 text-sm font-semibold">
                            <span>Total al mes</span>
                            <x-money :amount="$commitmentsTotal" />
                        </li>
                    </ul>
                @endif

                <form x-cloak x-show="adding" method="POST" action="{{ route('commitments.store') }}" class="mt-4 space-y-4 rounded-2xl bg-slate-50/80 p-4 ring-1 ring-slate-200/70">
                    @csrf
                    <input type="hidden" name="from" value="setup">
                    @include('commitments._form')
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1">Agregar gasto fijo</button>
                        @if ($commitments->isNotEmpty())
                            <button type="button" class="btn-secondary" @click="adding = false">Cancelar</button>
                        @endif
                    </div>
                </form>

                @if ($commitments->isEmpty())
                    <p class="mt-3 text-xs text-slate-500">¿No tienes gastos fijos? Sigue al paso 2.</p>
                @endif
            </x-card>

            {{-- Pasos 2 y 3: un solo formulario --}}
            <form id="setup-form" method="POST" action="{{ route('periods.save') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-card>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Paso 2</p>
                    <h2 class="text-lg font-semibold">Tu ingreso y ahorro</h2>
                    <p class="muted">Cuánto esperas recibir este mes y cuánto quieres guardar apenas cobres.</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <x-field label="Ingreso del mes (S/)" name="expected_income">
                            <input id="expected_income" name="expected_income" x-model="expected" type="text" inputmode="decimal" class="input text-lg font-semibold tabular-nums" required placeholder="0.00">
                        </x-field>
                        <x-field label="Quiero ahorrar (S/)" name="savings_target">
                            <input id="savings_target" name="savings_target" x-model="savings" type="text" inputmode="decimal" class="input text-lg font-semibold tabular-nums" required placeholder="0.00">
                        </x-field>
                    </div>
                    <template x-if="confirmed !== null">
                        <p class="mt-3 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800">Ya registraste ingresos reales por <b x-text="fc.money(confirmed)"></b>; el plan usa ese monto.</p>
                    </template>
                </x-card>

                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Paso 3</p>
                            <h2 class="text-lg font-semibold">Tus categorías de gasto</h2>
                            <p class="muted">Reparte lo que te queda en tus gastos del día a día. Aquí anotarás cada gasto.</p>
                        </div>
                        <button type="button" class="btn-secondary shrink-0" @click="add()">+ Categoría</button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template x-for="suggestion in missingSuggestions()" :key="suggestion">
                            <button type="button" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-emerald-100 hover:text-emerald-800"
                                    @click="add(suggestion)" x-text="'+ ' + suggestion"></button>
                        </template>
                    </div>

                    <div class="mt-4 space-y-2">
                        <template x-for="(envelope, index) in envelopes" :key="envelope.key">
                            <div class="flex items-center gap-2 rounded-xl bg-slate-50/80 p-2 ring-1 ring-slate-200/70">
                                <input type="hidden" :name="'envelopes[' + index + '][id]'" :value="envelope.id ?? ''">
                                <input type="color" :name="'envelopes[' + index + '][color]'" x-model="envelope.color"
                                       class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border-0 bg-transparent p-0.5" aria-label="Color">
                                <input type="text" :name="'envelopes[' + index + '][name]'" x-model="envelope.name" placeholder="Nombre" required maxlength="60"
                                       class="input min-w-0 flex-1 bg-white">
                                <div class="relative w-32 shrink-0">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">S/</span>
                                    <input type="text" inputmode="decimal" :name="'envelopes[' + index + '][allocated]'" x-model="envelope.allocated" required
                                           class="input bg-white pl-8 text-right tabular-nums">
                                </div>
                                <button type="button" class="rounded-lg p-1.5 text-xl leading-none text-slate-300 hover:bg-rose-50 hover:text-rose-600" @click="remove(index)" aria-label="Quitar">&times;</button>
                            </div>
                        </template>
                    </div>

                    <div x-show="!envelopes.length" class="mt-4 rounded-2xl border-2 border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                        Toca una sugerencia de arriba o "+ Categoría" para empezar.
                    </div>
                </x-card>
            </form>

            @if ($previous)
                <form method="POST" action="{{ route('periods.copy-previous') }}">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">Copiar categorías del mes anterior</button>
                </form>
            @endif

            {{-- Ingresos reales --}}
            <x-card class="space-y-3" x-data="{ adding: false }">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Ingresos recibidos</h2>
                        <p class="muted">Opcional: registra lo que ya cobraste para que el plan use montos reales.</p>
                    </div>
                    @if ($period)
                        <button type="button" class="btn-secondary shrink-0" @click="adding = !adding">+ Ingreso</button>
                    @endif
                </div>

                @unless ($period)
                    <p class="text-sm text-slate-500">Guarda tu plan primero para registrar ingresos.</p>
                @endunless

                @if ($period)
                    <form x-cloak x-show="adding" method="POST" action="{{ route('incomes.store') }}" class="grid grid-cols-2 gap-3 rounded-2xl bg-slate-50/80 p-4 ring-1 ring-slate-200/70">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                        <x-field label="Monto">
                            <input name="amount" type="text" inputmode="decimal" class="input" required>
                        </x-field>
                        <x-field label="Moneda">
                            <select name="currency" class="input">
                                <option value="PEN">Soles</option>
                                <option value="USD">Dólares</option>
                            </select>
                        </x-field>
                        <x-field label="De dónde" class="col-span-2">
                            <input name="source" type="text" class="input" placeholder="Sueldo, freelance…" required maxlength="100">
                        </x-field>
                        <x-field label="Fecha">
                            <input name="received_at" type="date" class="input" value="{{ now()->toDateString() }}" required>
                        </x-field>
                        <label class="mt-6 flex items-center gap-2 text-sm">
                            <input type="checkbox" name="confirmed" value="1" class="rounded border-slate-300 text-emerald-600" checked> Ya lo recibí
                        </label>
                        <button type="submit" class="btn-primary col-span-2">Guardar ingreso</button>
                    </form>
                @endif

                @foreach ($incomes as $income)
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $income->source }}</p>
                            <p class="text-xs text-slate-500">{{ $income->received_at->format('d/m') }} · {{ $income->confirmed ? 'Recibido' : 'Esperado' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-money :amount="$income->amount" :currency="$income->currency" class="font-semibold" />
                            <form method="POST" action="{{ route('incomes.update', $income) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="period_id" value="{{ $income->period_id }}">
                                <input type="hidden" name="amount" value="{{ $income->amount }}">
                                <input type="hidden" name="currency" value="{{ $income->currency }}">
                                <input type="hidden" name="source" value="{{ $income->source }}">
                                <input type="hidden" name="received_at" value="{{ $income->received_at->toDateString() }}">
                                <input type="hidden" name="confirmed" value="{{ $income->confirmed ? 0 : 1 }}">
                                <button class="text-xs text-slate-500 underline">{{ $income->confirmed ? 'Marcar esperado' : 'Ya lo recibí' }}</button>
                            </form>
                            <form method="POST" action="{{ route('incomes.destroy', $income) }}" onsubmit="return confirm('¿Eliminar este ingreso?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-lg leading-none text-slate-300 hover:text-rose-600" aria-label="Eliminar">&times;</button>
                            </form>
                        </div>
                    </div>
                @endforeach

                @if ($usdRate === null && $incomes->where('currency', 'USD')->isNotEmpty())
                    <p class="text-xs text-amber-700">Tienes ingresos en dólares sin tipo de cambio. Configúralo en <a href="{{ route('settings') }}" class="underline">Ajustes</a>.</p>
                @endif
            </x-card>
        </div>

        {{-- Resumen en vivo --}}
        <aside class="space-y-4 lg:sticky lg:top-8">
            <section class="overflow-hidden rounded-3xl bg-ink-900 p-6 text-white shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Así se reparte tu ingreso</p>
                <p class="mt-2 text-3xl font-extrabold tracking-tight tabular-nums" x-text="fc.money(fc.fromCents(income()))"></p>

                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-white/10">
                    <div class="bg-sky-400 transition-all duration-500" :style="'width:' + share(fc.toCents(savings)) + '%'"></div>
                    <div class="bg-amber-400 transition-all duration-500" :style="'width:' + share(fc.toCents(commitments)) + '%'"></div>
                    <div class="bg-emerald-400 transition-all duration-500" :style="'width:' + share(allocated()) + '%'"></div>
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-300"><span class="h-2 w-2 rounded-full bg-sky-400"></span>Ahorro</dt><dd class="tabular-nums" x-text="fc.money(savings)"></dd></div>
                    <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-300"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Gastos fijos</dt><dd class="tabular-nums" x-text="fc.money(commitments)"></dd></div>
                    <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-300"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Categorías</dt><dd class="tabular-nums" x-text="fc.money(fc.fromCents(allocated()))"></dd></div>
                    <div class="flex justify-between border-t border-white/10 pt-3 font-semibold">
                        <dt>Sin asignar</dt>
                        <dd class="tabular-nums" :class="free() < 0 ? 'text-rose-300' : 'text-emerald-300'" x-text="fc.money(fc.fromCents(free()))"></dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl p-3 text-xs"
                     :class="income() === 0 ? 'bg-white/5 text-slate-300' : (free() < 0 ? 'bg-rose-500/15 text-rose-200' : (free() === 0 ? 'bg-emerald-500/15 text-emerald-200' : 'bg-white/5 text-slate-300'))">
                    <span x-show="income() === 0">Escribe tu ingreso del mes en el paso 2 para ver cómo se reparte.</span>
                    <span x-show="income() > 0 && free() < 0">Te pasaste por <b x-text="fc.money(fc.fromCents(-free()))"></b>: baja alguna categoría o el ahorro.</span>
                    <span x-show="income() > 0 && free() === 0">¡Perfecto! Cada sol de tu ingreso tiene un destino.</span>
                    <span x-show="income() > 0 && free() > 0">Aún puedes repartir <b x-text="fc.money(fc.fromCents(free()))"></b> en tus categorías o sumarlo al ahorro.</span>
                </div>
            </section>

            <button type="submit" form="setup-form" class="btn-primary w-full py-3 text-base">
                {{ $period ? 'Guardar cambios' : 'Guardar mes y empezar' }}
            </button>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        function setupPage(form, commitments, confirmed, fixedCount) {
            const palette = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
            const suggestions = [...@js(\App\Services\Budget\PeriodBuilder::DEFAULT_CATEGORIES), 'Supermercado', 'Transporte', 'Salud', 'Varios'];
            let seq = 0;

            return {
                expected: fc.toCents(form.expected_income) > 0 ? form.expected_income : '',
                savings: form.savings_target,
                commitments,
                confirmed,
                fixedCount,
                envelopes: form.envelopes.map((e, i) => ({ ...e, color: e.color || palette[i % palette.length], key: ++seq })),

                add(name = '') {
                    this.envelopes.push({ id: null, name, allocated: '0', color: palette[this.envelopes.length % palette.length], key: ++seq });
                    this.$nextTick(() => {
                        const inputs = document.querySelectorAll(name ? 'input[name$="[allocated]"]' : 'input[name$="[name]"]');
                        inputs[inputs.length - 1]?.select();
                    });
                },

                remove(index) {
                    this.envelopes.splice(index, 1);
                },

                missingSuggestions() {
                    const normalize = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
                    const existing = this.envelopes.map((e) => normalize(e.name));
                    return suggestions.filter((s) => !existing.includes(normalize(s)));
                },

                stepDone(step) {
                    if (step === 1) return this.fixedCount > 0;
                    if (step === 2) return fc.toCents(this.expected) > 0;
                    return this.envelopes.length > 0 && this.allocated() > 0;
                },

                income() {
                    return this.confirmed !== null ? fc.toCents(this.confirmed) : fc.toCents(this.expected);
                },
                allocated() {
                    return this.envelopes.reduce((sum, e) => sum + fc.toCents(e.allocated), 0);
                },
                free() {
                    return this.income() - fc.toCents(this.savings) - fc.toCents(this.commitments) - this.allocated();
                },
                share(cents) {
                    const base = Math.max(this.income(), fc.toCents(this.savings) + fc.toCents(this.commitments) + this.allocated());
                    return base > 0 ? (cents * 100 / base).toFixed(2) : 0;
                },
            };
        }
    </script>
@endpush
