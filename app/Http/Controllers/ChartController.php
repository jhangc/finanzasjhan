<?php

namespace App\Http\Controllers;

use App\Services\Budget\PeriodBuilder;
use App\Services\Charts\ChartData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartController extends Controller
{
    public function index(): View
    {
        return view('charts.index');
    }

    /** JSON para Chart.js: envelopes | monthly | debt */
    public function data(Request $request, string $type, ChartData $charts, PeriodBuilder $periods): JsonResponse
    {
        $user = $request->user();

        return response()->json(match ($type) {
            'envelopes' => $charts->envelopes($periods->current($user)),
            'monthly' => $charts->monthly($user),
            'debt' => $charts->debt($user),
        });
    }
}
