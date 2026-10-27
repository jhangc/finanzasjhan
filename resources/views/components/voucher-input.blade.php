@props(['payment' => null])

{{-- Vouchers del pago (fotos o PDF, varios). El formulario debe tener enctype="multipart/form-data". --}}
@php($existing = $payment?->vouchers ?? collect())

<div {{ $attributes }} x-data="{
        picked: [],
        transfer: new DataTransfer(),
        add(event) {
            for (const file of event.target.files) {
                this.transfer.items.add(file);
                this.picked.push({ name: file.name, preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null });
            }
            this.$refs.files.files = this.transfer.files;
        },
        drop(index) {
            const next = new DataTransfer();
            [...this.transfer.files].forEach((file, i) => { if (i !== index) next.items.add(file); });
            this.transfer = next;
            this.picked.splice(index, 1);
            this.$refs.files.files = this.transfer.files;
        } }">
    <span class="label">Vouchers (opcional)</span>

    @if ($existing->isNotEmpty())
        <div class="mb-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
            @foreach ($existing as $voucher)
                <div x-data="{ removing: false }" class="relative overflow-hidden rounded-xl ring-1" :class="removing ? 'opacity-40 ring-rose-300' : 'ring-slate-200'">
                    <a href="{{ route('vouchers.show', $voucher) }}" title="{{ $voucher->original_name }}" class="block"
                       @click.prevent="$dispatch('open-vouchers', { items: @js($payment->voucherGallery()), index: {{ $loop->index }} })">
                        @if ($voucher->isPdf())
                            <span class="flex h-20 items-center justify-center bg-rose-50 text-sm font-bold text-rose-600">PDF</span>
                        @else
                            <img src="{{ route('vouchers.show', $voucher) }}" alt="Voucher" class="h-20 w-full object-cover" loading="lazy">
                        @endif
                    </a>
                    <label class="flex cursor-pointer items-center justify-center gap-1 bg-white py-1 text-[11px] font-medium text-slate-500 hover:text-rose-600">
                        <input type="checkbox" name="remove_vouchers[]" value="{{ $voucher->id }}" x-model="removing" class="h-3 w-3 rounded border-slate-300 text-rose-600">
                        Quitar
                    </label>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Archivos nuevos elegidos --}}
    <template x-if="picked.length">
        <div class="mb-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
            <template x-for="(file, index) in picked" :key="index">
                <div class="relative overflow-hidden rounded-xl ring-2 ring-emerald-300">
                    <template x-if="file.preview"><img :src="file.preview" alt="" class="h-20 w-full object-cover"></template>
                    <template x-if="!file.preview"><span class="flex h-20 items-center justify-center bg-rose-50 text-sm font-bold text-rose-600">PDF</span></template>
                    <p class="truncate bg-white px-1.5 py-1 text-[11px] text-slate-600" x-text="file.name"></p>
                    <button type="button" @click="drop(index)" class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-base leading-none text-slate-500 shadow hover:text-rose-600" aria-label="Quitar">&times;</button>
                </div>
            </template>
        </div>
    </template>

    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-slate-200 p-3 transition hover:border-emerald-400 hover:bg-emerald-50/40">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
            <x-icon name="plus" class="h-5 w-5" />
        </span>
        <span class="min-w-0 flex-1 text-sm">
            <span class="block font-medium text-slate-700" x-text="picked.length ? 'Agregar más vouchers' : '{{ $existing->isNotEmpty() ? 'Agregar más vouchers' : 'Subir fotos o PDF del comprobante' }}'"></span>
            <span class="block text-xs text-slate-500">Puedes elegir varios · JPG, PNG, HEIC o PDF · máx. 5 MB c/u</span>
        </span>
        <input type="file" accept="image/*,.heic,application/pdf" multiple class="sr-only" @change="add($event); $event.target.value = ''">
    </label>
    {{-- Input real que se envía (se llena con los archivos acumulados). --}}
    <input x-ref="files" type="file" name="vouchers[]" multiple class="hidden">

    @if ($errors->has('vouchers') || $errors->has('vouchers.*'))
        <p class="mt-1 text-xs text-red-600">{{ $errors->first('vouchers') ?: $errors->first('vouchers.*') }}</p>
    @endif
</div>
