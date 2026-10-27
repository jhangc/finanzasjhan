@extends('layouts.app')

@section('title', 'Ajustes')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight">Ajustes</h1>

    <x-card>
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-3">
                <x-field label="Moneda principal" name="currency_default">
                    <select id="currency_default" name="currency_default" class="input">
                        @foreach (['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency_default', $user->currency_default) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Tipo de cambio USD" name="usd_rate" hint="Manual, p. ej. 3.750">
                    <input id="usd_rate" name="usd_rate" type="text" inputmode="decimal" class="input" value="{{ old('usd_rate', $user->usd_rate) }}">
                </x-field>
            </div>

            <x-field label="Cuenta por defecto" name="default_account_id" hint="De aquí salen los gastos que anotas rápido en Hoy (puedes cambiarla en cada gasto).">
                <select id="default_account_id" name="default_account_id" class="input">
                    <option value="">Ninguna</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('default_account_id', $user->default_account_id) === $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Tarjeta por defecto" name="default_card_id" hint='Se usa cuando escribes "tc" o "tarjeta" en el input rápido.'>
                <select id="default_card_id" name="default_card_id" class="input">
                    <option value="">Ninguna</option>
                    @foreach ($cards as $card)
                        <option value="{{ $card->id }}" @selected((int) old('default_card_id', $user->default_card_id) === $card->id)>{{ $card->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Correo para recordatorios" name="reminder_email" hint="Si lo dejas vacío se usa el correo de tu cuenta.">
                <input id="reminder_email" name="reminder_email" type="email" class="input" value="{{ old('reminder_email', $user->reminder_email) }}" placeholder="{{ $user->email }}">
            </x-field>

            <button type="submit" class="btn-primary w-full">Guardar ajustes</button>
        </form>
    </x-card>

    {{-- Reglas recurrentes --}}
    <x-card class="space-y-3" x-data="{ adding: false, kind: 'envelope' }">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold">Reglas recurrentes</h2>
                <p class="text-xs text-slate-500">Se aplican el día 1 al planificar el mes nuevo.</p>
            </div>
            <button type="button" class="btn-secondary" @click="adding = !adding">+ Regla</button>
        </div>

        <form x-cloak x-show="adding" method="POST" action="{{ route('recurring-rules.store') }}" class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3">
            @csrf
            <x-field label="Tipo" class="col-span-2">
                <select name="kind" x-model="kind" class="input">
                    <option value="envelope">Crear una categoría cada mes</option>
                    <option value="commitment_due">Fijar el monto de un gasto fijo</option>
                </select>
            </x-field>
            <x-field label="Nombre" class="col-span-2">
                <input name="name" type="text" class="input" required maxlength="60" placeholder="Regalos, Mascota…">
            </x-field>
            <x-field label="Gasto fijo" class="col-span-2" x-show="kind === 'commitment_due'">
                <select name="commitment_id" class="input">
                    @foreach ($commitments as $commitment)
                        <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Monto (S/)">
                <input name="amount" type="text" inputmode="decimal" class="input" required>
            </x-field>
            <x-field label="Día del mes (opcional)">
                <input name="day_of_month" type="number" min="1" max="31" class="input">
            </x-field>
            <button type="submit" class="btn-primary col-span-2">Guardar regla</button>
        </form>

        @forelse ($rules as $rule)
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-2 text-sm">
                <div>
                    <p @class(['font-medium', 'line-through text-slate-400' => ! $rule->active])>{{ $rule->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $rule->kind === 'envelope' ? 'Categoría' : 'Monto de '.($rule->commitment?->name ?? '—') }}
                        @if ($rule->day_of_month) · día {{ $rule->day_of_month }} @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <x-money :amount="$rule->amount" class="font-semibold" />
                    <form method="POST" action="{{ route('recurring-rules.update', $rule) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" value="{{ $rule->name }}">
                        <input type="hidden" name="kind" value="{{ $rule->kind }}">
                        <input type="hidden" name="commitment_id" value="{{ $rule->commitment_id }}">
                        <input type="hidden" name="amount" value="{{ $rule->amount }}">
                        <input type="hidden" name="day_of_month" value="{{ $rule->day_of_month }}">
                        <input type="hidden" name="active" value="{{ $rule->active ? 0 : 1 }}">
                        <button class="text-xs text-slate-500 underline">{{ $rule->active ? 'Pausar' : 'Activar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('recurring-rules.destroy', $rule) }}" onsubmit="return confirm('¿Eliminar esta regla?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-lg text-slate-400 hover:text-red-600" aria-label="Eliminar">&times;</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Sin reglas.</p>
        @endforelse
    </x-card>

    {{-- Exportar / importar --}}
    <x-card class="space-y-3">
        <h2 class="font-semibold">Tus datos</h2>
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('export', ['format' => 'json']) }}" class="btn-secondary">Respaldo JSON</a>
            <a href="{{ route('export', ['format' => 'csv']) }}" class="btn-secondary">Movimientos CSV</a>
        </div>
        <form method="POST" action="{{ route('import') }}" enctype="multipart/form-data" class="space-y-2 border-t border-slate-100 pt-3">
            @csrf
            <x-field label="Importar respaldo JSON o CSV" name="file" hint="CSV con columnas: fecha, categoria, monto, nota, tarjeta. Lo repetido no se duplica.">
                <input id="file" name="file" type="file" accept=".json,.csv,.txt" class="block w-full text-sm" required>
            </x-field>
            <button type="submit" class="btn-primary w-full">Importar</button>
        </form>
    </x-card>

    {{-- Usuarios --}}
    <x-card class="space-y-3" x-data="{ adding: @js($errors->newUser->any()) }">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold">Usuarios</h2>
                <p class="text-xs text-slate-500">Cada usuario tiene sus propias cuentas y movimientos; no ve los tuyos.</p>
            </div>
            <button type="button" class="btn-secondary py-1.5" @click="adding = !adding" x-text="adding ? 'Cancelar' : '+ Nuevo usuario'"></button>
        </div>

        <form x-cloak x-show="adding" method="POST" action="{{ route('users.store') }}" class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
            @csrf
            @if ($errors->newUser->any())
                <ul class="rounded-lg bg-rose-50 p-2 text-sm text-rose-700 sm:col-span-2">
                    @foreach ($errors->newUser->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div>
                <label class="label" for="new_name">Nombre</label>
                <input id="new_name" name="name" type="text" class="input" value="{{ old('name') }}" required maxlength="255">
            </div>
            <div>
                <label class="label" for="new_email">Correo</label>
                <input id="new_email" name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="off">
            </div>
            <div>
                <label class="label" for="new_password">Contraseña</label>
                <input id="new_password" name="password" type="password" class="input" required autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="new_password_confirmation">Repítela</label>
                <input id="new_password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-primary sm:col-span-2">Crear usuario</button>
        </form>

        <ul class="divide-y divide-slate-100">
            @foreach ($users as $u)
                <li class="flex items-center justify-between py-2 text-sm">
                    <span class="font-semibold">{{ $u->name }} @if ($u->is(auth()->user()))<span class="badge bg-emerald-50 text-emerald-700">Tú</span>@endif</span>
                    <span class="text-slate-500">{{ $u->email }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
@endsection
