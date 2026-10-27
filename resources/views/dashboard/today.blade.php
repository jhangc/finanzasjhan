@extends('layouts.app')

@section('title', 'Hoy')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
@endpush

@section('content')
<div x-data="todayPage(@js($state))" class="space-y-5">

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ ucfirst(now()->locale('es')->translatedFormat('l j \d\e F')) }}</p>
            <h1 class="page-title">Hola, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }} 👋</h1>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge bg-white px-3 py-1.5 text-slate-600 ring-1 ring-slate-200">
                <x-icon name="calendar" class="h-4 w-4" /> <span x-text="state.month"></span>
            </span>
            <a href="{{ route('periods.setup') }}" class="btn-secondary py-1.5">Planificar mes</a>
            <a href="{{ route('transactions.create', ['kind' => 'income']) }}" class="btn py-1.5 bg-emerald-600 text-white hover:bg-emerald-500"><x-icon name="plus" class="h-4 w-4" /> Ingreso</a>
            <a href="{{ route('transactions.create') }}" class="btn py-1.5 bg-rose-500 text-white hover:bg-rose-400"><x-icon name="plus" class="h-4 w-4" /> Gasto</a>
        </div>
    </div>

    {{-- Alertas de vencimiento --}}
    <template x-if="state.commitments.alerts.length">
        <div class="flex flex-wrap gap-2">
            <template x-for="alert in state.commitments.alerts" :key="alert.id">
                <a :href="'{{ url('commitments') }}/' + alert.id"
                   class="inline-flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 ring-1 ring-rose-200 hover:bg-rose-100">
                    <x-icon name="bell" class="h-4 w-4" />
                    <span x-text="alert.name"></span>
                    <span class="font-semibold" x-text="dueText(alert.days_until)"></span>
                </a>
            </template>
        </div>
    </template>

    {{-- Fila 1: héroe + cascada --}}
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="relative overflow-hidden rounded-3xl bg-ink-900 p-6 text-white shadow-soft sm:p-8 lg:col-span-2">
            <div class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full blur-3xl transition-colors duration-700"
                 :class="negative() ? 'bg-rose-500/25' : 'bg-emerald-500/25'"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-teal-400/10 blur-3xl"></div>

            <div class="relative">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Puedes gastar hoy</p>
                        <p class="mt-2 text-5xl font-extrabold tracking-tight tabular-nums sm:text-6xl"
                           :class="negative() ? 'text-rose-300' : 'text-white'"
                           x-text="fc.money(state.cascade.todayAllowance)">{{ \App\Support\Money::format($state['cascade']['todayAllowance']) }}</p>
                        <p class="mt-2 text-sm text-slate-400">
                            Te quedan <span class="font-semibold text-white tabular-nums" x-text="fc.money(state.cascade.remaining)"></span>
                            para <span class="font-semibold text-white" x-text="state.cascade.daysLeft"></span>
                            <span x-text="state.cascade.daysLeft === 1 ? 'día' : 'días'"></span>
                        </p>
                    </div>
                    <div class="hidden rounded-2xl bg-white/5 p-3 ring-1 ring-white/10 sm:block">
                        <x-icon name="wallet" class="h-7 w-7 text-emerald-400" />
                    </div>
                </div>

                {{-- Avance del gasto del mes --}}
                <div class="mt-6">
                    <div class="mb-1.5 flex justify-between text-xs text-slate-400">
                        <span>Gastado <b class="text-white tabular-nums" x-text="fc.money(state.cascade.spent)"></b> de <span class="tabular-nums" x-text="fc.money(state.cascade.allocated)"></span></span>
                        <span x-text="spentPercent() + '%'"></span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full transition-all duration-700"
                             :class="spentPercent() > 100 ? 'bg-rose-400' : (spentPercent() >= 80 ? 'bg-amber-400' : 'bg-emerald-400')"
                             :style="'width:' + Math.min(100, spentPercent()) + '%'"></div>
                    </div>
                </div>

                {{-- Input rápido --}}
                <form @submit.prevent="submit()" class="mt-6 flex gap-2 rounded-2xl bg-white/10 p-1.5 ring-1 ring-white/15 backdrop-blur focus-within:ring-emerald-400/60">
                    <div class="flex items-center pl-3 text-emerald-400"><x-icon name="bolt" class="h-5 w-5" /></div>
                    <input x-ref="input" x-model="input" type="text" enterkeyhint="send" autocomplete="off"
                           @if ($autofocus) autofocus @endif
                           placeholder="Anota un gasto: 25 comida" aria-label="Registrar gasto"
                           class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2.5 text-base text-white placeholder:text-slate-400 focus:outline-none focus:ring-0">
                    <button type="submit" class="btn bg-emerald-500 text-ink-900 hover:bg-emerald-400" :disabled="busy || !input.trim()">
                        <span x-show="!busy">Anotar</span>
                        <span x-show="busy" x-cloak>…</span>
                    </button>
                </form>
                {{-- De dónde sale el dinero --}}
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold text-slate-300">Pagas con:</span>
                    <template x-for="option in state.sources.options" :key="option.value">
                        <button type="button" @click="setSource(option.value)"
                                class="rounded-full px-3 py-1.5 font-medium ring-1 transition"
                                :class="source === option.value ? (option.type === 'card' ? 'bg-amber-400 text-ink-900 ring-amber-400' : 'bg-emerald-400 text-ink-900 ring-emerald-400') : 'bg-white/5 text-slate-300 ring-white/10 hover:bg-white/10'">
                            <span x-text="option.name"></span>
                            <span class="opacity-70" x-text="' · ' + option.hint"></span>
                        </button>
                    </template>
                    <button type="button" @click="setSource('none')" class="rounded-full px-3 py-1.5 font-medium ring-1 transition"
                            :class="source === 'none' ? 'bg-slate-300 text-ink-900 ring-slate-300' : 'bg-white/5 text-slate-400 ring-white/10 hover:bg-white/10'">Otro</button>
                    <template x-if="!state.sources.options.length">
                        <a href="{{ route('accounts.index') }}" class="text-emerald-300 underline">Agrega tus cuentas para que el saldo baje solo</a>
                    </template>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                    <span>Prueba:</span>
                    <template x-for="example in examples()" :key="example">
                        <button type="button" class="rounded-full bg-white/5 px-2.5 py-1 font-medium text-slate-300 ring-1 ring-white/10 hover:bg-white/10"
                                @click="input = example; $refs.input.focus()" x-text="example"></button>
                    </template>
                    <span class="hidden sm:inline">· o nombra la cuenta al final: <b class="text-slate-300">25 comida efectivo</b></span>
                </div>

                {{-- Elegir sobre --}}
                <div x-cloak x-show="choosing" x-transition class="mt-4 rounded-2xl bg-white p-4 text-slate-900">
                    <p class="mb-3 text-sm text-slate-600">¿En qué categoría va <b class="text-slate-900" x-text="fc.money(pending?.amount)"></b>?</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="envelope in pending?.envelopes ?? []" :key="envelope.id">
                            <button type="button" class="btn-secondary py-1.5" @click="choose(envelope.id)">
                                <span class="h-2.5 w-2.5 rounded-full" :style="'background:' + (envelope.color || '#94a3b8')"></span>
                                <span x-text="envelope.name"></span>
                            </button>
                        </template>
                        <button type="button" class="btn py-1.5 text-slate-500 hover:text-slate-900" @click="reset()">Cancelar</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Cascada --}}
        <x-card class="flex flex-col">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Reparto de tu ingreso</h2>
                <span class="badge" :class="state.cascade.incomeIsConfirmed ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                      x-text="state.cascade.incomeIsConfirmed ? 'Ingreso real' : 'Ingreso esperado'"></span>
            </div>

            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="bg-sky-500 transition-all duration-700" :style="'width:' + share(state.cascade.savings) + '%'" title="Ahorro"></div>
                <div class="bg-amber-400 transition-all duration-700" :style="'width:' + share(state.cascade.commitments) + '%'" title="Gastos fijos"></div>
                <div class="bg-emerald-500 transition-all duration-700" :style="'width:' + share(state.cascade.allocated) + '%'" title="Categorías"></div>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Ingreso</dt><dd class="font-semibold tabular-nums" x-text="fc.money(state.cascade.income)"></dd></div>
                <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-500"><span class="h-2 w-2 rounded-full bg-sky-500"></span>Ahorro</dt><dd class="tabular-nums" x-text="'− ' + fc.money(state.cascade.savings)"></dd></div>
                <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-500"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Gastos fijos</dt><dd class="tabular-nums" x-text="'− ' + fc.money(state.cascade.commitments)"></dd></div>
                <div class="flex justify-between border-t border-dashed border-slate-200 pt-3"><dt class="font-medium">Libre para categorías</dt><dd class="font-semibold tabular-nums" :class="cents(state.cascade.availableForEnvelopes) < 0 ? 'text-rose-600' : ''" x-text="fc.money(state.cascade.availableForEnvelopes)"></dd></div>
                <div class="flex justify-between"><dt class="flex items-center gap-2 text-slate-500"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Presupuesto en categorías</dt><dd class="tabular-nums" x-text="fc.money(state.cascade.allocated)"></dd></div>
            </dl>

            <div class="mt-auto pt-4">
                <div x-show="cents(state.cascade.overAllocated) > 0" class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700">
                    Asignaste <b x-text="fc.money(state.cascade.overAllocated)"></b> más de lo disponible.
                </div>
                <div x-show="cents(state.cascade.incomeGap) > 0" class="mt-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                    Te faltan <b x-text="fc.money(state.cascade.incomeGap)"></b> de ingreso para cubrir el mes.
                </div>
                <div x-show="cents(state.cascade.overAllocated) === 0 && cents(state.cascade.incomeGap) === 0" class="rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800">
                    Tu mes está cubierto. Sin asignar: <b x-text="fc.money(fc.fromCents(cents(state.cascade.availableForEnvelopes) - cents(state.cascade.allocated)))"></b>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Fila 2: indicadores --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $tiles = [
                ['label' => 'Gastado', 'value' => 'state.cascade.spent', 'icon' => 'arrow-down', 'tone' => 'bg-rose-50 text-rose-600', 'hint' => "'en ' + state.cascade.envelopes.length + ' categorías'"],
                ['label' => 'Restante', 'value' => 'state.cascade.remaining', 'icon' => 'wallet', 'tone' => 'bg-emerald-50 text-emerald-600', 'hint' => "state.cascade.daysLeft + ' días por delante'"],
                ['label' => 'Por pagar', 'value' => 'state.commitments.pending', 'icon' => 'card', 'tone' => 'bg-amber-50 text-amber-600', 'hint' => "'pagado ' + fc.money(state.commitments.paid)"],
                ['label' => 'Ahorro del mes', 'value' => 'state.cascade.savings', 'icon' => 'piggy', 'tone' => 'bg-sky-50 text-sky-600', 'hint' => "'separado al inicio'"],
            ];
        @endphp
        @foreach ($tiles as $tile)
            <x-card class="p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $tile['label'] }}</p>
                    <span class="rounded-xl p-2 {{ $tile['tone'] }}"><x-icon :name="$tile['icon']" class="h-4 w-4" /></span>
                </div>
                <p class="mt-3 text-xl font-bold tracking-tight tabular-nums sm:text-2xl" x-text="fc.money({{ $tile['value'] }})"></p>
                <p class="mt-1 truncate text-xs text-slate-500" x-text="{{ $tile['hint'] }}"></p>
            </x-card>
        @endforeach
    </div>

    {{-- Fila 3: sobres + dona --}}
    <div class="grid gap-5 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Tus categorías</h2>
                    <p class="muted">Lo que te queda para gastar en cada una</p>
                </div>
                <a href="{{ route('periods.setup') }}" class="text-sm font-medium text-emerald-700 hover:underline">Editar</a>
            </div>

            <template x-if="!state.cascade.envelopes.length">
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center">
                    <x-icon name="inbox" class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-2 text-sm text-slate-500">Aún no tienes categorías.</p>
                    <a href="{{ route('periods.setup') }}" class="btn-primary mt-4">Crear categorías</a>
                </div>
            </template>

            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="envelope in state.cascade.envelopes" :key="envelope.id">
                    <div class="rounded-2xl p-4 ring-1 transition"
                         :class="envelope.state === 'over' ? 'bg-rose-50/60 ring-rose-200' : 'bg-slate-50/70 ring-slate-200/70 hover:ring-slate-300'">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-2 text-sm font-semibold">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + (envelope.color || '#94a3b8')"></span>
                                <span class="truncate" x-text="envelope.name"></span>
                            </span>
                            <span class="badge"
                                  :class="{ 'bg-emerald-100 text-emerald-700': envelope.state === 'ok', 'bg-amber-100 text-amber-700': envelope.state === 'warning', 'bg-rose-600 text-white': envelope.state === 'over' }"
                                  x-text="envelope.state === 'over' ? 'Excedido' : envelope.percent + '%'"></span>
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight tabular-nums"
                           :class="envelope.state === 'over' ? 'text-rose-600' : 'text-slate-900'" x-text="fc.money(envelope.remaining)"></p>
                        <p class="text-xs text-slate-500">
                            <span x-text="envelope.state === 'over' ? 'pasado del límite' : 'disponible'"></span>
                            · gastado <span class="tabular-nums" x-text="fc.money(envelope.spent)"></span> de <span class="tabular-nums" x-text="fc.money(envelope.allocated)"></span>
                        </p>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200/80">
                            <div class="h-full rounded-full transition-all duration-700"
                                 :class="{ 'bg-emerald-500': envelope.state === 'ok', 'bg-amber-500': envelope.state === 'warning', 'bg-rose-600': envelope.state === 'over' }"
                                 :style="'width:' + Math.min(100, envelope.percent) + '%'"></div>
                        </div>
                    </div>
                </template>
            </div>
        </x-card>

        <x-card class="flex flex-col">
            <h2 class="font-semibold">¿En qué se va?</h2>
            <p class="muted">Gastos de categorías y pagos fijos del mes</p>
            <div class="relative mx-auto mt-4 h-52 w-52" x-show="totalOut() > 0">
                <canvas x-ref="donut"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-xs text-slate-500">Total</span>
                    <span class="text-lg font-bold tabular-nums" x-text="fc.money(fc.fromCents(totalOut()))"></span>
                </div>
            </div>
            <div x-show="totalOut() === 0" class="flex flex-1 flex-col items-center justify-center py-10 text-center">
                <div class="h-32 w-32 rounded-full border-[14px] border-slate-100"></div>
                <p class="mt-4 text-sm text-slate-500">Anota tu primer gasto y verás aquí la distribución.</p>
            </div>
            <ul class="mt-5 space-y-2 text-sm" x-show="totalOut() > 0">
                <template x-for="envelope in topSpending()" :key="envelope.id">
                    <li class="flex items-center justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + envelope.color"></span>
                            <span class="truncate text-slate-600" x-text="envelope.name"></span>
                        </span>
                        <span class="font-medium tabular-nums" x-text="fc.money(envelope.spent)"></span>
                    </li>
                </template>
            </ul>
        </x-card>
    </div>

    {{-- Fila 4: últimos gastos + próximos pagos --}}
    <div class="grid gap-5 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">Últimos gastos</h2>
                <a href="{{ route('history.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">Ver historial</a>
            </div>
            <template x-if="!state.recent.length">
                <p class="py-8 text-center text-sm text-slate-500">Todavía no registras gastos este mes.</p>
            </template>
            <ul class="divide-y divide-slate-100">
                <template x-for="movement in state.recent" :key="movement.id">
                    <li class="flex items-center gap-3 py-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white"
                              :style="'background:' + (movement.color || '#94a3b8')" x-text="movement.envelope.charAt(0).toUpperCase()"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold" x-text="movement.note || movement.envelope"></p>
                            <p class="truncate text-xs text-slate-500">
                                <span x-text="movement.envelope"></span> · <span x-text="movement.when"></span>
                                <template x-if="movement.account"><span> · <span class="font-medium text-emerald-700" x-text="movement.account"></span></span></template>
                                <template x-if="movement.card"><span> · <span class="font-medium text-amber-700" x-text="movement.card"></span></span></template>
                            </p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums" x-text="'− ' + fc.money(movement.amount)"></span>
                    </li>
                </template>
            </ul>
        </x-card>

        <x-card>
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">Próximos pagos</h2>
                <a href="{{ route('commitments.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">Ver todos</a>
            </div>
            <template x-if="!state.commitments.upcoming.length">
                <div class="py-8 text-center">
                    <p class="text-sm text-slate-500" x-text="cents(state.commitments.total) > 0 ? '¡Todo pagado este mes!' : 'Aún no registras gastos fijos.'"></p>
                    <a href="{{ route('commitments.index') }}" class="btn-secondary mt-3" x-show="cents(state.commitments.total) === 0">Agregar gasto fijo</a>
                </div>
            </template>
            <ul class="divide-y divide-slate-100">
                <template x-for="item in state.commitments.upcoming" :key="item.id">
                    <li>
                        <a :href="'{{ url('commitments') }}/' + item.id" class="flex items-center gap-3 py-3">
                            <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl ring-1"
                                 :class="item.overdue || item.days_until <= 2 ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200'">
                                <span class="text-sm font-bold leading-none" x-text="item.date.split('/')[0]"></span>
                                <span class="text-[10px] uppercase leading-none" x-text="monthShort(item.date)"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold" x-text="item.name"></p>
                                <p class="text-xs" :class="item.overdue || item.days_until <= 2 ? 'font-semibold text-rose-600' : 'text-slate-500'" x-text="dueText(item.days_until)"></p>
                            </div>
                            <span class="text-sm font-semibold tabular-nums" x-text="fc.money(item.amount)"></span>
                        </a>
                    </li>
                </template>
            </ul>
            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                <div class="mb-1.5 flex justify-between text-xs text-slate-500">
                    <span>Pagado <b class="text-slate-700" x-text="fc.money(state.commitments.paid)"></b></span>
                    <span>de <span x-text="fc.money(state.commitments.total)"></span></span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-amber-400 transition-all duration-700" :style="'width:' + paidPercent() + '%'"></div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Aviso flotante --}}
    <div x-cloak x-show="message" x-transition
         class="fixed inset-x-4 bottom-24 z-40 mx-auto max-w-md rounded-2xl px-4 py-3 text-center text-sm font-medium text-white shadow-lg lg:bottom-8"
         :class="error ? 'bg-rose-600' : 'bg-ink-900'" x-text="message"></div>
</div>
@endsection

@push('scripts')
    <script>
        function todayPage(initial) {
            const palette = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
            const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            let chart = null; // fuera del estado reactivo de Alpine

            return {
                state: initial,
                input: '',
                busy: false,
                choosing: false,
                pending: null,
                message: '',
                error: false,
                timer: null,
                source: 'none',

                setSource(value) {
                    this.source = value;
                    try { localStorage.setItem('fc.source', value); } catch (e) {}
                },

                init() {
                    let saved = null;
                    try { saved = localStorage.getItem('fc.source'); } catch (e) {}
                    const valid = (v) => v === 'none' || this.state.sources.options.some((o) => o.value === v);
                    this.source = saved && valid(saved) ? saved : this.state.sources.default;
                    this.$nextTick(() => this.drawChart());
                    this.$watch('state', () => this.drawChart());
                },

                cents(value) { return fc.toCents(value); },
                negative() { return this.cents(this.state.cascade.todayAllowance) < 0; },

                spentPercent() {
                    const allocated = this.cents(this.state.cascade.allocated);
                    const spent = this.cents(this.state.cascade.spent);
                    if (allocated <= 0) return spent > 0 ? 100 : 0;
                    return Math.round(spent * 100 / allocated);
                },

                paidPercent() {
                    const total = this.cents(this.state.commitments.total);
                    return total > 0 ? Math.min(100, Math.round(this.cents(this.state.commitments.paid) * 100 / total)) : 0;
                },

                /** Porcentaje del ingreso que ocupa un monto (barra de la cascada). */
                share(value) {
                    const c = this.state.cascade;
                    const base = Math.max(this.cents(c.income), this.cents(c.savings) + this.cents(c.commitments) + this.cents(c.allocated));
                    return base > 0 ? (this.cents(value) * 100 / base).toFixed(2) : 0;
                },

                colorOf(envelope, index) { return envelope.color || palette[index % palette.length]; },

                /** Categorías con gasto + una porción con los pagos de gastos fijos y deudas. */
                slices() {
                    const list = this.state.cascade.envelopes.map((e, i) => ({ id: e.id, name: e.name, spent: e.spent, color: this.colorOf(e, i) }));
                    if (this.cents(this.state.cascade.paidThisMonth) > 0) {
                        list.push({ id: 'fixed', name: 'Gastos fijos y deudas', spent: this.state.cascade.paidThisMonth, color: '#64748b' });
                    }
                    return list;
                },

                totalOut() {
                    return this.cents(this.state.cascade.spent) + this.cents(this.state.cascade.paidThisMonth);
                },

                topSpending() {
                    return this.slices()
                        .filter((e) => this.cents(e.spent) > 0)
                        .sort((a, b) => this.cents(b.spent) - this.cents(a.spent))
                        .slice(0, 6);
                },

                examples() {
                    const names = this.state.cascade.envelopes.slice(0, 2).map((e) => e.name.split(' ')[0].toLowerCase());
                    return names.length ? [`25 ${names[0]}`, `12.50 ${names[1] ?? names[0]}`] : ['25 comida', '12.50 taxi'];
                },

                monthShort(date) { return months[parseInt(date.split('/')[1], 10) - 1] ?? ''; },

                dueText(days) {
                    if (days < 0) return 'vencido hace ' + Math.abs(days) + (Math.abs(days) === 1 ? ' día' : ' días');
                    if (days === 0) return 'vence hoy';
                    if (days === 1) return 'vence mañana';
                    return 'vence en ' + days + ' días';
                },

                drawChart() {
                    if (!window.Chart || !this.$refs.donut) return;
                    const envelopes = this.slices();
                    const data = {
                        labels: envelopes.map((e) => e.name),
                        datasets: [{
                            data: envelopes.map((e) => this.cents(e.spent) / 100), // solo para dibujar
                            backgroundColor: envelopes.map((e) => e.color),
                            borderWidth: 0,
                            hoverOffset: 6,
                        }],
                    };
                    if (chart) {
                        chart.data = data;
                        chart.update();
                        return;
                    }
                    chart = new Chart(this.$refs.donut, {
                        type: 'doughnut',
                        data,
                        options: {
                            cutout: '74%',
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: (ctx) => ' ' + fc.money(fc.fromCents(Math.round(ctx.raw * 100))) } },
                            },
                        },
                    });
                },

                flash(text, isError = false) {
                    this.message = text;
                    this.error = isError;
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.message = '', 3500);
                },

                reset() {
                    this.choosing = false;
                    this.pending = null;
                    this.$refs.input.focus();
                },

                async send(body) {
                    this.busy = true;
                    const { ok, status, data } = await fc.api(@js(route('movements.quick')), { body });
                    this.busy = false;

                    if (status === 429) return this.flash('Vas muy rápido. Espera un momento.', true);
                    if (!ok) {
                        const first = data.errors ? Object.values(data.errors)[0][0] : null;
                        return this.flash(data.message && !first ? data.message : (first || 'No se pudo registrar.'), true);
                    }

                    if (data.status === 'choose') {
                        this.pending = { ...data, input: body.input };
                        this.choosing = true;
                        if (!data.envelopes.length) this.flash('Primero crea tus categorías en "Planificar".', true);
                        return;
                    }

                    this.state = data.state;
                    this.input = '';
                    this.choosing = false;
                    this.pending = null;
                    this.flash('✓ ' + data.message);
                    this.$refs.input.focus();
                },

                submit() {
                    if (!this.input.trim() || this.busy) return;
                    this.send({ input: this.input, source: this.source });
                },

                choose(envelopeId) {
                    this.send({
                        input: this.pending.input,
                        envelope_id: envelopeId,
                        amount: this.pending.amount,
                        paid_with_card: this.pending.paid_with_card,
                        note: this.pending.note,
                        source: this.pending.source ?? this.source,
                    });
                },
            };
        }
    </script>
@endpush
