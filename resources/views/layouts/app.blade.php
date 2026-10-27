<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.head')
    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @php
        $nav = [
            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Hoy', 'icon' => 'home'],
            ['route' => 'periods.setup', 'match' => 'periods.*', 'label' => 'Planificar', 'icon' => 'calendar'],
            ['route' => 'commitments.index', 'match' => 'commitments.*', 'label' => 'Gastos fijos', 'icon' => 'banknotes'],
            ['route' => 'accounts.index', 'match' => 'accounts.*', 'label' => 'Cuentas', 'icon' => 'card'],
            ['route' => 'history.index', 'match' => 'history.*', 'label' => 'Historial', 'icon' => 'clock'],
            ['route' => 'charts.index', 'match' => 'charts.*', 'label' => 'Gráficos', 'icon' => 'chart'],
        ];
        $user = auth()->user();
    @endphp

    {{-- Barra lateral (escritorio) --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-ink-900 text-slate-300 lg:flex">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-6 py-6">
            <img src="{{ asset('icons/icon.svg') }}" alt="" class="h-9 w-9">
            <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name') }}</span>
        </a>

        <div class="px-3 pb-4">
            <a href="{{ route('transactions.create') }}" class="flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-3 py-2.5 text-sm font-bold text-ink-900 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400">
                <x-icon name="plus" class="h-5 w-5" /> Anotar gasto o ingreso
            </a>
        </div>

        <nav class="flex-1 space-y-1 px-3">
            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-widest text-slate-500">Menú</p>
            @foreach ($nav as $item)
                @php($active = request()->routeIs($item['match']))
                <a href="{{ route($item['route']) }}" @class([
                    'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                    'bg-white/10 text-white' => $active,
                    'hover:bg-white/5 hover:text-white' => ! $active,
                ])>
                    <x-icon :name="$item['icon']" @class(['h-5 w-5', 'text-emerald-400' => $active, 'text-slate-500 group-hover:text-slate-300' => ! $active]) />
                    {{ $item['label'] }}
                </a>
            @endforeach

            <p class="px-3 pb-2 pt-6 text-[11px] font-semibold uppercase tracking-widest text-slate-500">Cuenta</p>
            <a href="{{ route('settings') }}" @class([
                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                'bg-white/10 text-white' => request()->routeIs('settings'),
                'hover:bg-white/5 hover:text-white' => ! request()->routeIs('settings'),
            ])>
                <x-icon name="cog" @class(['h-5 w-5', 'text-emerald-400' => request()->routeIs('settings'), 'text-slate-500 group-hover:text-slate-300' => ! request()->routeIs('settings')]) />
                Ajustes
            </a>
        </nav>

        <div class="m-3 flex items-center gap-3 rounded-2xl bg-white/5 p-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-sm font-bold text-emerald-300">
                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-1.5 text-slate-500 hover:bg-white/10 hover:text-white" title="Salir">
                    <x-icon name="logout" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Barra superior (celular) --}}
        <header class="sticky top-0 z-20 flex items-center justify-between bg-ink-900 px-4 py-3 text-white lg:hidden">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                <img src="{{ asset('icons/icon.svg') }}" alt="" class="h-8 w-8">
                <span class="font-bold tracking-tight">{{ config('app.name') }}</span>
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ route('settings') }}" class="rounded-lg p-2 text-slate-400 hover:text-white" title="Ajustes"><x-icon name="cog" /></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:text-white" title="Salir"><x-icon name="logout" /></button>
                </form>
            </div>
        </header>

        <main class="w-full space-y-5 px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-12 lg:pt-8">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)"
                     class="flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('bottom')

    <x-voucher-gallery />

    {{-- Botón flotante "Anotar" (celular) --}}
    @unless (request()->routeIs('transactions.*'))
        <a href="{{ route('transactions.create') }}" aria-label="Anotar"
           class="fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] right-4 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-ink-900 shadow-xl shadow-emerald-600/30 transition active:scale-95 lg:hidden">
            <x-icon name="plus" class="h-7 w-7" />
        </a>
    @endunless

    {{-- Navegación inferior (celular) --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
        <ul class="grid grid-cols-6">
            @foreach ($nav as $item)
                @php($active = request()->routeIs($item['match']))
                <li>
                    <a href="{{ route($item['route']) }}" @class([
                        'flex flex-col items-center gap-1 px-0.5 py-2.5 text-center text-[10px] font-medium leading-tight',
                        'text-emerald-600' => $active,
                        'text-slate-500' => ! $active,
                    ])>
                        <x-icon :name="$item['icon']" class="h-6 w-6" />
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @stack('scripts')
</body>
</html>
