<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickMovementRequest;
use App\Http\Requests\StoreMovementRequest;
use App\Http\Requests\UpdateMovementRequest;
use App\Models\Envelope;
use App\Models\Movement;
use App\Services\Budget\PeriodBuilder;
use App\Services\Budget\TodayState;
use App\Services\Movements\QuickEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MovementController extends Controller
{
    /** Input rápido: "25 comida". Responde JSON para Alpine. */
    public function quick(QuickMovementRequest $request, PeriodBuilder $periods, QuickEntryService $quick, TodayState $state): JsonResponse
    {
        $this->authorize('create', Movement::class);

        $period = $periods->current($request->user());
        if ($period === null) {
            return response()->json(['status' => QuickEntryService::INVALID, 'message' => 'Primero planifica tu mes.'], 422);
        }

        $result = $quick->handle($request->user(), $period, $request->validated());

        if ($result['status'] === QuickEntryService::INVALID) {
            return response()->json($result, 422);
        }

        if ($result['status'] === QuickEntryService::CREATED) {
            $movement = $result['movement'];
            $result['movement'] = [
                'id' => $movement->id,
                'amount' => $movement->amount,
                'envelope' => $movement->envelope->name,
                'paid_with_commitment_id' => $movement->paid_with_commitment_id,
            ];
            $result['state'] = $state->for($period->fresh());
        }

        return response()->json($result, $result['status'] === QuickEntryService::CREATED ? 201 : 200);
    }

    public function store(StoreMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', Movement::class);

        $data = $request->validated();
        $data['spent_at'] ??= now();

        $request->user()->movements()->create($data);

        return back()->with('status', 'Gasto registrado.');
    }

    public function update(UpdateMovementRequest $request, Movement $movement): RedirectResponse
    {
        $this->authorize('update', $movement);

        $data = $request->validated();
        $data['spent_at'] ??= $movement->spent_at;

        // Mover un gasto a otro sobre solo dentro del mismo mes.
        $target = Envelope::find($data['envelope_id']);
        if ($target->period_id !== $movement->envelope->period_id) {
            return back()->withErrors(['envelope_id' => 'La categoría debe ser del mismo mes.']);
        }

        $movement->update($data);

        return back()->with('status', 'Gasto actualizado.');
    }

    public function destroy(Movement $movement): RedirectResponse
    {
        $this->authorize('delete', $movement);

        $movement->delete();

        return back()->with('status', 'Gasto eliminado.');
    }
}
