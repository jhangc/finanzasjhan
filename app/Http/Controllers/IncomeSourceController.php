<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Categorías de ingreso (atajos de "De dónde viene") que cada usuario arma a su gusto.
 */
class IncomeSourceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $name = trim($request->validate(['name' => ['required', 'string', 'max:100']])['name']);
        $user = $request->user();

        $user->addIncomeSource($name);

        return response()->json(['sources' => $user->incomeSourceList()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:100']])['name'];
        $user = $request->user();

        $user->update(['income_sources' => array_values(array_diff($user->incomeSourceList(), [$name]))]);

        return response()->json(['sources' => $user->incomeSourceList()]);
    }
}
