@props(['percent' => 0, 'state' => 'ok'])

@php($width = max(0, min(100, (int) $percent)))

<div {{ $attributes->class('h-2 w-full overflow-hidden rounded-full bg-slate-200') }}>
    <div @class([
        'h-full rounded-full',
        'bg-emerald-500' => $state === 'ok',
        'bg-amber-500' => $state === 'warning',
        'bg-red-600' => $state === 'over',
    ]) style="width: {{ $state === 'over' ? 100 : $width }}%"></div>
</div>
