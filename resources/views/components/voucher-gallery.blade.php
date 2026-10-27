{{--
    Visor de vouchers (una sola instancia en el layout).
    Se abre con: $dispatch('open-vouchers', { items: [{ url, pdf, name }], index: 0 })
--}}
<div x-data="{
        open: false, items: [], index: 0,
        show(detail) { this.items = detail.items; this.index = detail.index ?? 0; this.open = true; },
        get current() { return this.items[this.index] ?? null; },
        prev() { this.index = (this.index - 1 + this.items.length) % this.items.length; },
        next() { this.index = (this.index + 1) % this.items.length; } }"
     @open-vouchers.window="show($event.detail)"
     @keydown.escape.window="open = false"
     @keydown.arrow-left.window="open && items.length > 1 && prev()"
     @keydown.arrow-right.window="open && items.length > 1 && next()"
     x-cloak x-show="open" x-transition.opacity
     class="fixed inset-0 z-[60] flex flex-col bg-ink-900/95 text-white">

    {{-- Barra superior --}}
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold" x-text="current?.name"></p>
            <p class="text-xs text-slate-400" x-show="items.length > 1" x-text="(index + 1) + ' de ' + items.length"></p>
        </div>
        <div class="flex items-center gap-2">
            <a :href="current?.url" target="_blank" class="rounded-lg px-3 py-1.5 text-sm text-slate-300 ring-1 ring-white/15 hover:bg-white/10">Abrir aparte</a>
            <button type="button" @click="open = false" class="rounded-lg p-1.5 text-3xl leading-none text-slate-300 hover:bg-white/10" aria-label="Cerrar">&times;</button>
        </div>
    </div>

    {{-- Voucher actual --}}
    <div class="relative flex min-h-0 flex-1 items-center justify-center px-4" @click.self="open = false">
        <template x-if="current && !current.pdf">
            <img :src="current.url" :alt="current.name" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl">
        </template>
        <template x-if="current && current.pdf">
            <iframe :src="current.url" class="h-full w-full max-w-4xl rounded-lg bg-white"></iframe>
        </template>

        <template x-if="items.length > 1">
            <div>
                <button type="button" @click="prev()" class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl hover:bg-white/20" aria-label="Anterior">&#8249;</button>
                <button type="button" @click="next()" class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl hover:bg-white/20" aria-label="Siguiente">&#8250;</button>
            </div>
        </template>
    </div>

    {{-- Miniaturas --}}
    <div class="flex justify-center gap-2 overflow-x-auto px-4 py-3" x-show="items.length > 1">
        <template x-for="(item, i) in items" :key="item.url">
            <button type="button" @click="index = i"
                    class="h-14 w-14 shrink-0 overflow-hidden rounded-lg ring-2 transition"
                    :class="i === index ? 'ring-emerald-400' : 'opacity-60 ring-transparent hover:opacity-100'">
                <template x-if="!item.pdf"><img :src="item.url" alt="" class="h-full w-full object-cover"></template>
                <template x-if="item.pdf"><span class="flex h-full w-full items-center justify-center bg-rose-50 text-xs font-bold text-rose-600">PDF</span></template>
            </button>
        </template>
    </div>
</div>
