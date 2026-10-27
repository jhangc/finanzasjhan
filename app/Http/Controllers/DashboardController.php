<?php

namespace App\Http\Controllers;

use App\Services\Budget\PeriodBuilder;
use App\Services\Budget\TodayState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Pantalla "Hoy". Sin periodo del mes actual → a armar el mes. */
    public function __invoke(Request $request, PeriodBuilder $periods, TodayState $state): View|RedirectResponse
    {
        $period = $periods->current($request->user());

        if ($period === null) {
            return redirect()->route('periods.setup');
        }

        $this->authorize('view', $period);

        return view('dashboard.today', [
            'period' => $period,
            'state' => $state->for($period),
            'autofocus' => $request->boolean('pwa'),
        ]);
    }
}
