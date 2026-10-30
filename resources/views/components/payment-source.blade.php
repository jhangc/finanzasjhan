{{-- De dónde sale el dinero de un pago: name="source" con "account:ID" | "card:ID" | "none".
     cardsDisabled: expresión Alpine (una tarjeta se paga desde una cuenta, no con otra tarjeta). --}}
@props(['payment' => null, 'cardsDisabled' => 'false', 'label' => '¿Desde dónde pagas?'])

@php
    $user = auth()->user();
    $accounts = \App\Models\Account::ownedBy($user)->orderBy('sort_order')->orderBy('name')->get();
    $cards = \App\Models\Commitment::ownedBy($user)->where('is_card', true)->where('active', true)->orderBy('name')->get();
    $selected = match (true) {
        $payment?->account_id !== null => "account:{$payment->account_id}",
        $payment?->paid_with_commitment_id !== null => "card:{$payment->paid_with_commitment_id}",
        $payment !== null => 'none',
        $user->default_account_id !== null => "account:{$user->default_account_id}",
        default => 'none',
    };
@endphp

<x-field :label="$label" name="source" {{ $attributes }}>
    <select name="source" class="input bg-white">
        @foreach ($accounts as $account)
            <option value="account:{{ $account->id }}" @selected($selected === "account:{$account->id}")>
                {{ $account->name }} · Saldo {{ \App\Support\Money::format($account->balance, $account->currency) }}
            </option>
        @endforeach
        @if ($cards->isNotEmpty())
            <optgroup label="Tarjetas de crédito">
                @foreach ($cards as $card)
                    <option value="card:{{ $card->id }}" :disabled="{{ $cardsDisabled }}" @selected($selected === "card:{$card->id}")>{{ $card->name }}</option>
                @endforeach
            </optgroup>
        @endif
        <option value="none" @selected($selected === 'none')>Otro / no descontar de ninguna cuenta</option>
    </select>
</x-field>
