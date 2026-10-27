<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\Income;
use Illuminate\Http\RedirectResponse;

class IncomeController extends Controller
{
    public function store(StoreIncomeRequest $request): RedirectResponse
    {
        $this->authorize('create', Income::class);

        $request->user()->incomes()->create($request->validated());

        return back()->with('status', 'Ingreso registrado.');
    }

    public function update(UpdateIncomeRequest $request, Income $income): RedirectResponse
    {
        $this->authorize('update', $income);

        $income->update($request->validated());

        return back()->with('status', 'Ingreso actualizado.');
    }

    public function destroy(Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);

        $income->delete();

        return back()->with('status', 'Ingreso eliminado.');
    }
}
