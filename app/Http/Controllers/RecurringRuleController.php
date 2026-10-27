<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringRuleRequest;
use App\Http\Requests\UpdateRecurringRuleRequest;
use App\Models\RecurringRule;
use Illuminate\Http\RedirectResponse;

class RecurringRuleController extends Controller
{
    public function store(StoreRecurringRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringRule::class);

        $request->user()->recurringRules()->create($request->validated());

        return back()->with('status', 'Regla creada.');
    }

    public function update(UpdateRecurringRuleRequest $request, RecurringRule $recurringRule): RedirectResponse
    {
        $this->authorize('update', $recurringRule);

        $recurringRule->update($request->validated());

        return back()->with('status', 'Regla actualizada.');
    }

    public function destroy(RecurringRule $recurringRule): RedirectResponse
    {
        $this->authorize('delete', $recurringRule);

        $recurringRule->delete();

        return back()->with('status', 'Regla eliminada.');
    }
}
