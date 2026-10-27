<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0b1220">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                colors: { ink: { 900: '#0b1220', 800: '#111a2e', 700: '#1b2640' } },
                boxShadow: { soft: '0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12)' },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer components {
        .label { @apply mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500; }
        .input { @apply w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-base text-slate-900 placeholder:text-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10; }
        .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition active:scale-[.98] disabled:opacity-50 disabled:active:scale-100; }
        .btn-primary { @apply btn bg-emerald-600 text-white shadow-sm shadow-emerald-600/20 hover:bg-emerald-500; }
        .btn-dark { @apply btn bg-ink-900 text-white hover:bg-ink-700; }
        .btn-secondary { @apply btn bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900; }
        .btn-danger { @apply btn bg-white text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50; }
        .badge { @apply inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold; }
        .page-title { @apply text-2xl font-bold tracking-tight text-slate-900; }
        .muted { @apply text-sm text-slate-500; }
    }
    [x-cloak] { display: none !important; }
    body { font-feature-settings: 'cv11', 'ss01'; }
</style>
<script src="{{ asset('js/app.js') }}"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
