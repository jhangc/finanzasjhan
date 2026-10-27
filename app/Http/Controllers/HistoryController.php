<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Models\Period;
use App\Services\Budget\PeriodBuilder;
use App\Services\History\HistoryFeed;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request, PeriodBuilder $periods, HistoryFeed $feed): View
    {
        $user = $request->user();

        $period = $request->filled('period')
            ? Period::ownedBy($user)->findOrFail($request->integer('period'))
            : $periods->current($user);

        if ($period) {
            $this->authorize('view', $period);
        }

        $type = array_key_exists($request->query('type'), HistoryFeed::TYPES) ? $request->query('type') : null;

        return view('history.index', [
            'period' => $period,
            'periods' => $user->periods()->orderByDesc('year')->orderByDesc('month')->get(),
            'type' => $type,
            'items' => $period ? $feed->for($period, $type) : collect(),
            'envelopes' => $period?->envelopes ?? collect(),
            'cards' => Commitment::ownedBy($user)->where('is_card', true)->orderBy('name')->get(),
            'commitments' => Commitment::ownedBy($user)->orderBy('name')->get(),
        ]);
    }
}
