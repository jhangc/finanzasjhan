@props(['label', 'name' => null, 'hint' => null])

<div {{ $attributes }}>
    <label @if ($name) for="{{ $name }}" @endif class="label">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if ($name)
        @error($name)
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
