{{-- Modal para crear varias categorías a la vez. Requiere en el x-data padre: creatingCategories --}}
@php
    $existingNames = collect($targets)->firstWhere('key', 'categories')['options']->pluck('name')
        ->map(fn ($n) => \App\Services\Parsing\QuickEntryParser::normalize($n));
    $defaults = collect(\App\Services\Budget\PeriodBuilder::DEFAULT_CATEGORIES)
        ->reject(fn ($n) => $existingNames->contains(\App\Services\Parsing\QuickEntryParser::normalize($n)))
        ->values();
@endphp

<x-modal show="creatingCategories" title="Nuevas categorías" size="max-w-lg">
    <form method="POST" action="{{ route('envelopes.batch') }}"
          x-data="{
              rows: @js($defaults->map(fn ($name) => ['name' => $name, 'allocated' => '', 'checked' => $existingNames->isEmpty()])->all()),
              add() { this.rows.push({ name: '', allocated: '', checked: true }); this.$nextTick(() => this.$el.querySelectorAll('input[data-name]')[this.rows.length - 1]?.focus()); },
              get count() { return this.rows.filter(r => r.checked && r.name.trim()).length; },
          }"
          class="space-y-4">
        @csrf
        <input type="hidden" name="redirect_to" value="transactions">

        <p class="text-sm text-slate-500">Marca las que quieras crear. El monto es cuánto piensas gastar al mes en cada una (opcional, lo puedes ajustar luego en Planificar).</p>

        <div class="space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="flex items-center gap-2 rounded-xl p-2 ring-1 transition"
                     :class="row.checked ? 'bg-emerald-50/60 ring-emerald-200' : 'bg-white ring-slate-200'">
                    <input type="checkbox" x-model="row.checked" class="h-5 w-5 shrink-0 rounded border-slate-300 text-emerald-600">
                    <input type="text" data-name x-model="row.name" :name="row.checked ? 'categories[' + i + '][name]' : ''"
                           class="input min-w-0 flex-1 bg-white py-2" placeholder="Nombre" maxlength="60" @input="row.checked = true">
                    <div class="relative w-28 shrink-0">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">S/</span>
                        <input type="text" inputmode="decimal" x-model="row.allocated" :name="row.checked ? 'categories[' + i + '][allocated]' : ''"
                               class="input bg-white py-2 pl-8 text-right tabular-nums" placeholder="0">
                    </div>
                </div>
            </template>
        </div>

        <button type="button" @click="add()" class="w-full rounded-xl p-2.5 text-sm font-semibold text-slate-500 ring-1 ring-dashed ring-slate-300 hover:bg-slate-50 hover:text-slate-900">
            + Otra categoría
        </button>

        <button type="submit" class="btn-primary w-full py-3" :disabled="count === 0"
                x-text="count === 1 ? 'Crear 1 categoría' : 'Crear ' + count + ' categorías'"></button>
    </form>
</x-modal>
