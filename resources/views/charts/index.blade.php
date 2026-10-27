@extends('layouts.app')

@section('title', 'Gráficos')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
@endpush

@section('content')
    <div>
        <h1 class="page-title">Gráficos</h1>
        <p class="muted">Cómo se mueve tu dinero mes a mes.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <x-card>
            <h2 class="font-semibold">Gasto del mes por categoría</h2>
            <p class="muted mb-4">Distribución del mes actual</p>
            <div class="relative mx-auto h-72 max-w-xs"><canvas id="chart-envelopes"></canvas></div>
            <p id="chart-envelopes-empty" class="hidden py-16 text-center text-sm text-slate-500">Sin gastos este mes.</p>
        </x-card>

        <x-card class="lg:col-span-2">
            <h2 class="font-semibold">Últimos 6 meses</h2>
            <p class="muted mb-4">Ingresos, gastos en categorías y pagos de deuda</p>
            <div class="relative h-72"><canvas id="chart-monthly"></canvas></div>
        </x-card>
    </div>

    <x-card>
        <h2 class="font-semibold">Deuda total</h2>
        <p class="muted mb-4">Saldo de todas tus deudas al cierre de cada mes</p>
        <div class="relative h-72"><canvas id="chart-debt"></canvas></div>
    </x-card>
@endsection

@push('scripts')
    <script>
        (async () => {
            // Chart.js recibe números solo para dibujar; los montos llegan como strings exactos.
            const toNumber = (value) => fc.toCents(value) / 100;
            const tooltip = { callbacks: { label: (ctx) => ' ' + (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + fc.money(fc.fromCents(Math.round(ctx.raw * 100))) } };
            const moneyAxis = { ticks: { callback: (value) => 'S/ ' + value.toLocaleString('es-PE') } };
            const get = async (type) => (await fetch(@js(url('charts/data')) + '/' + type, { headers: { Accept: 'application/json' } })).json();

            const [envelopes, monthly, debt] = await Promise.all([get('envelopes'), get('monthly'), get('debt')]);

            const envelopeData = envelopes.data.map(toNumber);
            if (envelopeData.some((v) => v > 0)) {
                new Chart(document.getElementById('chart-envelopes'), {
                    type: 'doughnut',
                    data: { labels: envelopes.labels, datasets: [{ data: envelopeData, backgroundColor: envelopes.colors, borderWidth: 0 }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip } },
                });
            } else {
                document.getElementById('chart-envelopes').parentElement.classList.add('hidden');
                document.getElementById('chart-envelopes-empty').classList.remove('hidden');
            }

            new Chart(document.getElementById('chart-monthly'), {
                type: 'bar',
                data: {
                    labels: monthly.labels,
                    datasets: [
                        { label: 'Ingresos', data: monthly.income.map(toNumber), backgroundColor: '#10b981', borderRadius: 6 },
                        { label: 'Gastos en categorías', data: monthly.spent.map(toNumber), backgroundColor: '#3b82f6', borderRadius: 6 },
                        { label: 'Pagos de deuda', data: monthly.debt.map(toNumber), backgroundColor: '#f59e0b', borderRadius: 6 },
                    ],
                },
                options: { maintainAspectRatio: false, scales: { y: moneyAxis }, plugins: { legend: { position: 'bottom' }, tooltip } },
            });

            new Chart(document.getElementById('chart-debt'), {
                type: 'line',
                data: {
                    labels: debt.labels,
                    datasets: [{ label: 'Deuda total', data: debt.data.map(toNumber), borderColor: '#dc2626', backgroundColor: '#dc262622', fill: true, tension: 0.3 }],
                },
                options: { maintainAspectRatio: false, scales: { y: moneyAxis }, plugins: { legend: { display: false }, tooltip } },
            });
        })();
    </script>
@endpush
