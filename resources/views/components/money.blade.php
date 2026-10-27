@props(['amount', 'currency' => 'PEN'])

<span {{ $attributes->class(['tabular-nums whitespace-nowrap', 'text-red-600' => str_starts_with((string) $amount, '-')]) }}>{{ \App\Support\Money::format($amount, $currency) }}</span>
