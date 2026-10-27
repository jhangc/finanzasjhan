<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel de marca --}}
        <div class="relative hidden overflow-hidden bg-ink-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-teal-400/10 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <img src="{{ asset('icons/icon.svg') }}" alt="" class="h-10 w-10">
                <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
            </div>

            <div class="relative space-y-6">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">Sabe cuánto puedes<br>gastar <span class="text-emerald-400">hoy</span>.</h2>
                <p class="max-w-md text-slate-400">Presupuesto por categorías, gastos fijos bajo control y un solo número que importa cada día. Registrar un gasto toma 5 segundos.</p>
                <div class="max-w-sm rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Puedes gastar hoy</p>
                    <p class="mt-1 text-4xl font-extrabold tracking-tight">S/ 62.35</p>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full w-2/3 rounded-full bg-emerald-400"></div></div>
                </div>
            </div>

            <p class="relative text-xs text-slate-500">© {{ now()->year }} {{ config('app.name') }}</p>
        </div>

        {{-- Formulario --}}
        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img src="{{ asset('icons/icon.svg') }}" alt="" class="h-10 w-10">
                    <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight">@yield('title')</h1>
                <p class="mb-6 mt-1 text-sm text-slate-500">@yield('subtitle')</p>
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
