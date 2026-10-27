<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnvelopeRequest;
use App\Http\Requests\StoreEnvelopesBatchRequest;
use App\Http\Requests\UpdateEnvelopeRequest;
use App\Models\Envelope;
use App\Services\Budget\PeriodBuilder;
use Illuminate\Http\RedirectResponse;

class EnvelopeController extends Controller
{
    public function store(StoreEnvelopeRequest $request): RedirectResponse
    {
        $this->authorize('create', Envelope::class);

        $data = $request->validated();
        $data['sort_order'] = (int) Envelope::where('period_id', $data['period_id'])->max('sort_order') + 1;

        $request->user()->envelopes()->create($data);

        return back()->with('status', 'Categoría creada.');
    }

    /** Crea varias categorías en el mes actual (lo crea si aún no existe). */
    public function batch(StoreEnvelopesBatchRequest $request, PeriodBuilder $periods): RedirectResponse
    {
        $this->authorize('create', Envelope::class);

        $user = $request->user();
        $period = $periods->current($user) ?? $periods->generate($user, now()->year, now()->month);
        $this->authorize('update', $period);

        $created = $periods->addEnvelopes($period, $request->validated('categories'));

        $route = $request->input('redirect_to') === 'setup' ? 'periods.setup' : 'transactions.create';

        return redirect()->route($route)->with('status', match ($created) {
            0 => 'Esas categorías ya existían.',
            1 => 'Categoría creada.',
            default => "Se crearon {$created} categorías.",
        });
    }

    public function update(UpdateEnvelopeRequest $request, Envelope $envelope): RedirectResponse
    {
        $this->authorize('update', $envelope);

        $envelope->update($request->validated());

        return back()->with('status', 'Categoría actualizada.');
    }

    public function destroy(Envelope $envelope): RedirectResponse
    {
        $this->authorize('delete', $envelope);

        if ($envelope->movements()->exists()) {
            return back()->withErrors(['envelopes' => "No puedes eliminar «{$envelope->name}»: tiene gastos registrados."]);
        }

        $envelope->delete();

        return back()->with('status', 'Categoría eliminada.');
    }
}
