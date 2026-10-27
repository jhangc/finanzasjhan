{{-- Formulario de gasto fijo. Variables: $commitment (opcional), $defaultKind (opcional: fixed|debt|card|annual) --}}
@php
    $c = $commitment ?? null;
    $type = old('type', $c?->type ?? 'fixed');
    $kind = $type === 'debt' && old('is_card', $c?->is_card) ? 'card' : $type;
    if (! $c && old('type') === null && isset($defaultKind)) {
        $kind = $defaultKind;
    }

    $kinds = [
        'fixed' => ['Pago mensual', 'Alquiler, luz, agua, internet, celular…'],
        'debt' => ['Deuda o préstamo', 'Préstamo Yape, personal, banco, cuotas'],
        'card' => ['Tarjeta de crédito', 'Pagas una cuota y tiene día de corte'],
        'annual' => ['Pago anual', 'SOAT, seguro, predial… se reparte en 12'],
    ];

    $suggestions = [
        'fixed' => ['Alquiler', 'Luz', 'Agua', 'Internet', 'Celular', 'Gimnasio', 'Streaming'],
        'debt' => ['Préstamo Yape', 'Préstamo personal', 'Préstamo BCP', 'Departamento', 'Préstamo auto'],
        'card' => ['BCP Visa', 'Interbank Visa', 'BBVA Mastercard'],
        'annual' => ['SOAT', 'Seguro auto', 'Impuesto predial', 'Matrícula'],
    ];

    // Abre "opciones avanzadas" si hay inicial o un total distinto de cuotas × monto.
    $showAdvanced = filled(old('down_payment')) || filled(old('original_amount'))
        || ($c && $c->term_months && (\App\Support\Money::cmp($c->down_payment, '0') > 0
            || \App\Support\Money::cmp($c->original_amount, \App\Support\Money::mul($c->installment(), (string) $c->term_months)) !== 0));

    $annualTotal = old('annual_total', $c && $c->type === 'annual' ? \App\Support\Money::mul($c->monthly_amount, 12) : null);
@endphp

<div x-data="{ kind: @js($kind), annual: @js((string) $annualTotal),
        mode: @js(old('term_months', $c?->term_months) || ! ($c?->isDebt() && ! $c?->is_card) ? 'installments' : 'open'),
        installment: @js((string) old('installment_amount', $c?->installment_amount ?? ($c?->term_months ? $c->monthly_amount : null))),
        frequency: @js(old('frequency', $c?->frequency ?? 'monthly')),
        start: @js((string) old('start_date', $c?->start_date?->toDateString())),
        installmentsTotal() { return (parseInt(this.term) || 0) * fc.toCents(this.installment); },
        endDate() {
            const n = parseInt(this.term); if (!this.start || !n) return '';
            const d = new Date(this.start + 'T00:00:00');
            if (this.frequency === 'weekly') d.setDate(d.getDate() + 7 * (n - 1));
            else if (this.frequency === 'biweekly') d.setDate(d.getDate() + 15 * (n - 1));
            else { const day = d.getDate(); d.setDate(1); d.setMonth(d.getMonth() + n - 1); d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate())); }
            return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
        original: @js((string) old('original_amount', $c?->original_amount)), down: @js((string) old('down_payment', $c?->down_payment)), term: @js((string) old('term_months', $c?->term_months)), limit: @js((string) old('credit_limit', $c?->credit_limit)), used: @js((string) old('total_balance', $c?->is_card ? $c->total_balance : null)), statement: @js((string) old('statement_balance', $c?->statement_balance)), suggestions: @js($suggestions), financed() { return Math.max(0, fc.toCents(this.original) - fc.toCents(this.down)); } }" class="space-y-4">
    <input type="hidden" name="type" :value="kind === 'card' ? 'debt' : kind">
    <input type="hidden" name="is_card" :value="kind === 'card' ? 1 : 0">

    @unless ($lockKind ?? false)
        <div>
            <span class="label">¿Qué tipo de pago es?</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($kinds as $value => [$title, $description])
                    <button type="button" @click="kind = '{{ $value }}'"
                            :class="kind === '{{ $value }}' ? 'bg-emerald-50 ring-2 ring-emerald-500' : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50'"
                            class="rounded-xl p-3 text-left transition">
                        <span class="block text-sm font-semibold text-slate-900">{{ $title }}</span>
                        <span class="mt-0.5 block text-xs leading-snug text-slate-500">{{ $description }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endunless

    <x-field label="Nombre" name="name">
        <input id="name" x-ref="name" name="name" type="text" class="input" value="{{ old('name', $c?->name) }}" required maxlength="100" :placeholder="{ fixed: 'Ej. Internet', debt: 'Ej. Préstamo Yape', card: 'Ej. BCP Visa', annual: 'Ej. SOAT' }[kind]">
        @unless ($c)
            <div class="mt-2 flex flex-wrap gap-1.5">
                <template x-for="suggestion in suggestions[kind]" :key="suggestion">
                    <button type="button" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-emerald-100 hover:text-emerald-800"
                            @click="$refs.name.value = suggestion" x-text="suggestion"></button>
                </template>
            </div>
        @endunless
    </x-field>

    {{-- Préstamo o deuda: con cuotas (número, monto, periodo) o sin cuotas fijas --}}
    <template x-if="kind === 'debt'">
        <div class="space-y-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
                <button type="button" @click="mode = 'installments'" class="rounded-lg py-2 transition"
                        :class="mode === 'installments' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Con cuotas</button>
                <button type="button" @click="mode = 'open'" class="rounded-lg py-2 transition"
                        :class="mode === 'open' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">Sin cuotas fijas</button>
            </div>

            <template x-if="mode === 'installments'">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="term_months" label="Número de cuotas">
                            <input id="term_months" name="term_months" x-model="term" type="number" min="1" max="600" class="input" required placeholder="Ej. 3">
                        </x-field>
                        <x-field name="installment_amount" label="Monto por cuota (S/)">
                            <input id="installment_amount" name="installment_amount" x-model="installment" type="text" inputmode="decimal" class="input" required placeholder="Ej. 200">
                        </x-field>
                        <x-field name="frequency" label="Periodo">
                            <select id="frequency" name="frequency" x-model="frequency" class="input">
                                @foreach (\App\Models\Commitment::FREQUENCIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field name="start_date" label="Fecha de la 1.ª cuota">
                            <input id="start_date" name="start_date" x-model="start" type="date" class="input" required>
                        </x-field>
                    </div>

                    <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900" x-show="installmentsTotal() > 0">
                        Total a pagar: <b x-text="fc.money(fc.fromCents(installmentsTotal() + fc.toCents(down)))"></b>
                        <span class="block text-xs text-emerald-800/90">
                            <span x-text="term"></span> cuotas de <b x-text="fc.money(installment)"></b>
                            <span x-text="{ monthly: 'cada mes', biweekly: 'cada 15 días', weekly: 'cada semana' }[frequency]"></span>
                            <template x-if="endDate()"><span> · última cuota el <b x-text="endDate()"></b></span></template>
                        </span>
                    </div>

                    <div x-data="{ advanced: @js($showAdvanced) }">
                        <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-900" @click="advanced = !advanced">
                            <span x-text="advanced ? '▾' : '▸'"></span> Opciones avanzadas (inicial, total distinto, ya pagaste cuotas)
                        </button>
                        <div x-show="advanced" x-transition class="mt-3 grid gap-3 sm:grid-cols-2">
                            <x-field name="down_payment" label="Cuota inicial (si hubo)">
                                <input id="down_payment" name="down_payment" x-model="down" type="text" inputmode="decimal" class="input" placeholder="0">
                            </x-field>
                            @unless ($c)
                                <x-field name="down_payment_date" label="Fecha de la inicial">
                                    <input id="down_payment_date" name="down_payment_date" type="date" class="input" value="{{ old('down_payment_date', now()->toDateString()) }}">
                                </x-field>
                            @endunless
                            <x-field name="original_amount" label="Monto total, si es distinto (S/)" hint="Ej. departamento: precio total aunque las cuotas no lo cubran.">
                                <input id="original_amount" name="original_amount" x-model="original" type="text" inputmode="decimal" class="input" placeholder="Vacío = cuotas × monto">
                            </x-field>
                            <x-field name="total_balance" label="{{ $c ? 'Lo que te falta (S/)' : 'Te falta hoy (si ya pagaste cuotas)' }}">
                                <input id="total_balance" name="total_balance" type="text" inputmode="decimal" class="input" value="{{ old('total_balance', $c?->total_balance) }}" placeholder="{{ $c ? '' : 'Vacío = todo' }}">
                            </x-field>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="mode === 'open'">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-field name="total_balance" label="¿Cuánto debes? (S/)">
                        <input id="total_balance" name="total_balance" type="text" inputmode="decimal" class="input" value="{{ old('total_balance', $c?->total_balance) }}" required placeholder="0.00">
                    </x-field>
                    <x-field name="monthly_amount" label="Pagas al mes (opcional)">
                        <input id="monthly_amount" name="monthly_amount" type="text" inputmode="decimal" class="input" value="{{ old('monthly_amount', $c && \App\Support\Money::cmp($c->monthly_amount, '0') > 0 ? $c->monthly_amount : null) }}" placeholder="0.00">
                    </x-field>
                    <x-field name="pay_day" label="Día de pago (opcional)">
                        <input id="pay_day" name="pay_day" type="number" min="1" max="31" class="input" value="{{ old('pay_day', $c?->pay_day) }}" placeholder="Ej. 30">
                    </x-field>
                </div>
            </template>
        </div>
    </template>

    <template x-if="kind !== 'card' && kind !== 'debt'">
    <div class="grid grid-cols-2 gap-3">
        <template x-if="kind !== 'annual'">
            <x-field name="monthly_amount" label="Cuánto pagas al mes (S/)">
                <input id="monthly_amount" name="monthly_amount" type="text" inputmode="decimal" class="input" value="{{ old('monthly_amount', $c?->monthly_amount) }}" required placeholder="0.00">
                <p class="mt-1 text-xs text-slate-500" x-show="kind === 'debt'">El mínimo que te piden cada mes.</p>
            </x-field>
        </template>
        <template x-if="kind === 'annual'">
            <x-field name="monthly_amount" label="Cuánto pagas al año (S/)">
                <input name="annual_total" x-model="annual" type="text" inputmode="decimal" class="input" required placeholder="0.00">
                <p class="mt-1 text-xs text-emerald-700" x-show="fc.toCents(annual) > 0">
                    Separarás <b x-text="fc.money(fc.fromCents(Math.round(fc.toCents(annual) / 12)))"></b> al mes
                </p>
            </x-field>
        </template>

        <x-field name="pay_day" label="Día de pago">
            <input id="pay_day" name="pay_day" type="number" min="1" max="31" class="input" value="{{ old('pay_day', $c?->pay_day) }}"
                   :required="kind !== 'annual'" :placeholder="kind === 'annual' ? 'Opcional' : 'Ej. 15'">
        </x-field>
    </div>
    </template>

    {{-- Tarjeta de crédito: primero lo que da el banco y lo consumido, luego los pagos --}}
    <template x-if="kind === 'card'">
        <div class="space-y-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700">1. Lo que te da el banco</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-field name="credit_limit" label="Línea de crédito (S/)">
                        <input id="credit_limit" name="credit_limit" x-model="limit" type="text" inputmode="decimal" class="input" required placeholder="Ej. 8000">
                    </x-field>
                    <x-field name="total_balance" label="Consumido hasta hoy (S/)">
                        <input id="total_balance" name="total_balance" x-model="used" type="text" inputmode="decimal" class="input" required placeholder="0.00">
                    </x-field>
                    <div>
                        <span class="label">Disponible</span>
                        <p class="rounded-xl px-3.5 py-2.5 text-lg font-bold tabular-nums ring-1"
                           :class="fc.toCents(limit) - fc.toCents(used) < 0 ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                           x-text="fc.money(fc.fromCents(fc.toCents(limit) - fc.toCents(used)))"></p>
                    </div>
                </div>
            </div>

            <div>
                <p class="mb-1 text-sm font-semibold text-slate-700">2. Tu estado de cuenta (último corte)</p>
                <p class="mb-2 text-xs text-slate-500">Lo que te cobra el banco este mes. Lo que consumiste después del corte va al siguiente estado de cuenta.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="cut_day" label="Día de corte">
                        <input id="cut_day" name="cut_day" type="number" min="1" max="31" class="input" value="{{ old('cut_day', $c?->cut_day) }}" required placeholder="Ej. 10">
                    </x-field>
                    <x-field name="pay_day" label="Último día de pago">
                        <input id="pay_day" name="pay_day" type="number" min="1" max="31" class="input" value="{{ old('pay_day', $c?->pay_day) }}" required placeholder="Ej. 5">
                    </x-field>
                    <x-field name="statement_balance" label="Total a pagar (S/)">
                        <input id="statement_balance" name="statement_balance" x-model="statement" type="text" inputmode="decimal" class="input" placeholder="Pago total del estado de cuenta">
                    </x-field>
                    <x-field name="monthly_amount" label="Pago mínimo (S/)">
                        <input id="monthly_amount" name="monthly_amount" type="text" inputmode="decimal" class="input" value="{{ old('monthly_amount', $c && \App\Support\Money::cmp($c->monthly_amount, '0') > 0 ? $c->monthly_amount : null) }}" placeholder="Opcional">
                    </x-field>
                </div>
                <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2" x-show="fc.toCents(statement) > 0">
                    <p class="rounded-lg bg-amber-50 p-2.5 text-amber-900">
                        Tienes que pagar <b x-text="fc.money(statement)"></b> de este estado de cuenta.
                    </p>
                    <p class="rounded-lg bg-slate-50 p-2.5 text-slate-700" x-show="fc.toCents(used) - fc.toCents(statement) > 0">
                        Consumos después del corte: <b x-text="fc.money(fc.fromCents(fc.toCents(used) - fc.toCents(statement)))"></b>. Se cobran el próximo mes.
                    </p>
                </div>
            </div>
        </div>
    </template>


    <p x-show="kind === 'card'" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
        Cuando anotes un gasto terminando en <b>tc</b> (ej. <i>120 super tc</i>), se suma a la deuda de esta tarjeta.
    </p>

    @if ($c)
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('active', $c->active))>
            Activo (se descuenta de tu ingreso cada mes)
        </label>
    @endif
</div>
