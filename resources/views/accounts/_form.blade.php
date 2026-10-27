{{-- Formulario de cuenta de débito. Variables: $account (o null) --}}
@php($a = $account)

<div class="grid gap-3 sm:grid-cols-2">
    <x-field label="Nombre" :name="$a ? null : 'name'">
        <input name="name" type="text" class="input bg-white" value="{{ old('name', $a?->name) }}" required maxlength="60" placeholder="Ej. Cuenta sueldo">
    </x-field>
    <x-field label="Banco" :name="$a ? null : 'bank'">
        <input name="bank" type="text" class="input bg-white" value="{{ old('bank', $a?->bank) }}" maxlength="60" placeholder="BCP, Interbank, BBVA…" list="banks">
    </x-field>
    <x-field label="Tipo">
        <select name="type" class="input bg-white">
            @foreach (\App\Models\Account::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $a?->type ?? 'checking') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Moneda">
        <select name="currency" class="input bg-white">
            <option value="PEN" @selected(old('currency', $a?->currency ?? 'PEN') === 'PEN')>Soles (S/)</option>
            <option value="USD" @selected(old('currency', $a?->currency) === 'USD')>Dólares ($)</option>
        </select>
    </x-field>
    <x-field label="Saldo actual" :name="$a ? null : 'balance'" class="sm:col-span-2" hint="Lo que tienes hoy en esta cuenta.">
        <input name="balance" type="text" inputmode="decimal" class="input bg-white text-lg font-semibold tabular-nums" value="{{ old('balance', $a?->balance) }}" required placeholder="0.00">
    </x-field>
</div>

