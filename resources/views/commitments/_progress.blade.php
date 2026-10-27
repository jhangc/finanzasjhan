{{-- Avance de una deuda a plazo. Variables: $p (DebtProgress::for), $compact (bool) --}}
@php
    $compact ??= false;
    $statusStyles = [
        'ahead' => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'Vas adelantado'],
        'on_track' => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'Vas al día'],
        'behind' => ['bg-rose-50 text-rose-700 ring-rose-200', 'Vas atrasado'],
        'finished' => ['bg-emerald-50 text-emerald-800 ring-emerald-200', '¡Deuda pagada!'],
    ];
    [$statusClass, $statusLabel] = $statusStyles[$p['status']];
    $diff = \App\Support\Money::isNegative($p['difference']) ? \App\Support\Money::sub('0', $p['difference']) : $p['difference'];
@endphp

@if ($compact)
    <div class="mt-3">
        <div class="mb-1 flex justify-between text-xs text-slate-500">
            <span>Pagado <b class="text-slate-700"><x-money :amount="$p['paid']" /></b> de <x-money :amount="$p['original']" /></span>
            <span class="font-semibold text-slate-700">{{ $p['percent'] }}%</span>
        </div>
        <x-progress :percent="$p['percent']" :state="$p['status'] === 'behind' ? 'warning' : 'ok'" />
        <p class="mt-1.5 text-xs text-slate-500">
            @if ($p['finished'])
                ¡Terminaste de pagar!
            @elseif ($p['installments_due'] === 0)
                Primera cuota el {{ $p['first_due']?->format('d/m/Y') ?? '—' }} · {{ $p['term'] }} cuotas
            @else
                Cuota {{ $p['current_installment'] }} de {{ $p['term'] }} · termina {{ $p['end_date']?->locale('es')->translatedFormat('M Y') }}
            @endif
            @if (! $p['finished'] && $p['status'] !== 'on_track')
                · <span @class(['font-semibold', 'text-emerald-700' => $p['status'] === 'ahead', 'text-rose-600' => $p['status'] === 'behind'])>
                    {{ $p['status'] === 'ahead' ? 'amortizaste' : 'atrasado' }} <x-money :amount="$diff" />{{ $p['status'] === 'ahead' ? ' extra' : '' }}
                </span>
            @endif
        </p>
        @if ($p['needs_balloon'])
            <p class="mt-1 text-xs text-amber-700">Con el mínimo, al final quedarían <b><x-money :amount="$p['projected_final']" /></b> para hipoteca.</p>
        @endif
    </div>
@else
    <x-card class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">Avance de la deuda</h2>
                <p class="muted">
                    @if (\App\Support\Money::cmp($p['down_payment'], '0') > 0)
                        Inicial <x-money :amount="$p['down_payment']" /> · financiado <x-money :amount="$p['financed']" /> ·
                    @endif
                    {{ $p['term'] }} cuotas · 1.ª cuota {{ $p['first_due']?->format('d/m/Y') ?? '—' }} · termina {{ $p['end_date']?->locale('es')->translatedFormat('F Y') }}
                </p>
            </div>
            <span class="badge px-3 py-1 ring-1 {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>

        <div>
            <div class="mb-2 flex items-end justify-between">
                <div>
                    <p class="text-xs text-slate-500">Pagado</p>
                    <x-money :amount="$p['paid']" class="text-3xl font-extrabold tracking-tight text-emerald-700" />
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">Te falta</p>
                    <x-money :amount="$p['remaining']" class="text-3xl font-extrabold tracking-tight" />
                </div>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $p['percent'] }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-slate-500">{{ $p['percent'] }}% de <x-money :amount="$p['original']" /></p>
        </div>

        {{-- Línea de cuotas --}}
        <div>
            <div class="mb-2 flex justify-between text-xs text-slate-500">
                <span>Cuotas vencidas hasta hoy: <b class="text-slate-700">{{ $p['installments_due'] }} de {{ $p['term'] }}</b></span>
                <span>Quedan {{ $p['months_left'] }} {{ $p['months_left'] === 1 ? 'cuota' : 'cuotas' }}</span>
            </div>
            <div class="flex gap-0.5">
                @for ($i = 1; $i <= $p['term']; $i++)
                    <div @class([
                        'h-2 flex-1 rounded-sm',
                        'bg-emerald-500' => $i <= $p['installments_due'],
                        'bg-amber-400' => $i === $p['installments_due'] + 1 && ! $p['finished'],
                        'bg-slate-200' => $i > $p['installments_due'] + 1 || ($p['finished'] && $i > $p['installments_due']),
                    ]) title="Cuota {{ $i }}"></div>
                @endfor
            </div>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-3">
                <dt class="text-xs text-slate-500">Con {{ \App\Support\Money::cmp($p['down_payment'], '0') > 0 ? 'la inicial y ' : '' }}el mínimo ya deberías llevar</dt>
                <dd class="mt-1 font-semibold"><x-money :amount="$p['expected_paid']" /></dd>
            </div>
            <div class="rounded-xl p-3 ring-1 {{ $statusClass }}">
                <dt class="text-xs">{{ $p['status'] === 'behind' ? 'Te falta para estar al día' : 'Amortizado de más' }}</dt>
                <dd class="mt-1 font-semibold"><x-money :amount="$diff" /></dd>
            </div>
        </dl>

        @unless ($p['finished'])
            {{-- Proyección al final del plazo --}}
            <div @class([
                'rounded-2xl p-4 ring-1',
                'bg-amber-50 ring-amber-200' => $p['needs_balloon'],
                'bg-emerald-50 ring-emerald-200' => ! $p['needs_balloon'],
            ])>
                @if ($p['needs_balloon'])
                    <p class="text-sm font-semibold text-amber-900">Si solo pagas el mínimo, en {{ $p['end_date']?->locale('es')->translatedFormat('F Y') }} te quedarán</p>
                    <x-money :amount="$p['projected_final']" class="text-2xl font-extrabold tracking-tight text-amber-900" />
                    <p class="mt-1 text-xs text-amber-800">
                        Ese saldo iría a hipoteca. Cada amortización extra lo reduce.
                        Para no necesitar hipoteca tendrías que pagar <b><x-money :amount="$p['required_monthly']" /></b> por cuota.
                    </p>
                @else
                    <p class="text-sm font-semibold text-emerald-900">Con el mínimo terminas de pagar dentro del plazo, sin hipoteca.</p>
                @endif
            </div>

            {{-- Simulador --}}
            <div x-data="{
                    pay: @js($p['minimum']),
                    remaining: fc.toCents(@js($p['remaining'])),
                    months: {{ $p['months_left'] }},
                    get payCents() { return Math.max(0, fc.toCents(this.pay)); },
                    get final() { return Math.max(0, this.remaining - this.payCents * this.months); },
                    get monthsToFinish() { return this.payCents > 0 ? Math.ceil(this.remaining / this.payCents) : null; } }"
                 class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
                <p class="text-sm font-semibold">Simula cuánto pagar por cuota</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <div class="relative w-40">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">S/</span>
                        <input x-model="pay" type="text" inputmode="decimal" class="input bg-white pl-8 tabular-nums">
                    </div>
                    @foreach (array_unique([$p['minimum'], \App\Support\Money::mul($p['minimum'], '1.5'), \App\Support\Money::mul($p['minimum'], 2)]) as $option)
                        <button type="button" class="rounded-full bg-white px-3 py-1.5 text-xs font-medium ring-1 ring-slate-200 hover:ring-emerald-400"
                                @click="pay = @js($option)">{{ \App\Support\Money::format($option) }}</button>
                    @endforeach
                    @if ($p['needs_balloon'])
                        <button type="button" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                                @click="pay = @js($p['required_monthly'])">Sin hipoteca</button>
                    @endif
                </div>
                <p class="mt-3 text-sm" x-show="months > 0">
                    <template x-if="final > 0">
                        <span>Al terminar las <b x-text="months"></b> cuotas te quedarían <b class="text-amber-700" x-text="fc.money(fc.fromCents(final))"></b> para hipoteca.</span>
                    </template>
                    <template x-if="final === 0 && monthsToFinish !== null">
                        <span class="text-emerald-700">Terminas de pagar en <b x-text="monthsToFinish"></b> <span x-text="monthsToFinish === 1 ? 'cuota' : 'cuotas'"></span>, sin hipoteca<span x-show="monthsToFinish < months"> (<b x-text="months - monthsToFinish"></b> antes del plazo)</span>.</span>
                    </template>
                </p>
            </div>
        @endunless
    </x-card>
@endif
