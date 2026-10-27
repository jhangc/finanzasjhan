@props(['days' => null, 'paid' => false, 'startsOn' => null])

@if ($startsOn)
    <span class="badge bg-slate-100 text-slate-600">Desde el {{ $startsOn->format('d/m') }}</span>
@elseif ($paid)
    <span class="badge bg-emerald-100 text-emerald-700">Pagado</span>
@elseif ($days !== null && $days < 0)
    <span class="badge bg-red-600 text-white">Vencido hace {{ abs($days) }} {{ abs($days) === 1 ? 'día' : 'días' }}</span>
@elseif ($days === 0)
    <span class="badge bg-red-600 text-white">Vence hoy</span>
@elseif ($days !== null && $days <= \App\Services\Debts\CommitmentOverview::DUE_SOON_DAYS)
    <span class="badge bg-red-100 text-red-700">Vence en {{ $days }} {{ $days === 1 ? 'día' : 'días' }}</span>
@endif
