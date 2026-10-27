@props(['show', 'title', 'size' => 'max-w-2xl'])

{{-- Requiere un x-data en el padre con la variable $show. --}}
<div x-cloak x-show="{{ $show }}" x-transition.opacity
     class="fixed inset-0 z-50 !m-0 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
     @keydown.escape.window="{{ $show }} = false">
    <div @click.outside="{{ $show }} = false" class="max-h-[90vh] w-full {{ $size }} overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" @click="{{ $show }} = false" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Cerrar">&times;</button>
        </div>
        {{ $slot }}
    </div>
</div>
