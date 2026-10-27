{{-- Modal "registrar pago". Requiere en el x-data padre: paying (null | {id, name, amount}) --}}
<x-modal show="paying" title="Registrar pago" size="max-w-md">
    <form method="POST" action="{{ route('commitment-payments.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <input type="hidden" name="commitment_id" :value="paying?.id">
        <p class="text-sm text-slate-600" x-text="paying?.name"></p>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Monto (S/)">
                <input name="amount" type="text" inputmode="decimal" class="input text-lg tabular-nums" :value="paying?.amount && paying.amount !== '0.00' ? paying.amount : ''" required>
            </x-field>
            <x-field label="Fecha de pago">
                <input name="paid_at" type="date" class="input" value="{{ now()->toDateString() }}" required>
            </x-field>
        </div>
        <x-field label="Nota (opcional)">
            <input name="note" type="text" class="input" maxlength="255" placeholder="N.º de operación, banco…">
        </x-field>
        <x-voucher-input />
        <button type="submit" class="btn-primary w-full">Registrar pago</button>
    </form>
</x-modal>
