<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Commitment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('settings.index', [
            'user' => $user,
            'cards' => Commitment::ownedBy($user)->where('is_card', true)->orderBy('name')->get(),
            'accounts' => $user->accounts()->orderBy('sort_order')->get(),
            'commitments' => Commitment::ownedBy($user)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'rules' => $user->recurringRules()->with('commitment:id,name')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'Ajustes guardados.');
    }
}
