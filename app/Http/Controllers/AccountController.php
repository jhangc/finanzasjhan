<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Services\Accounts\AccountsOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, AccountsOverview $overview): View
    {
        $this->authorize('viewAny', Account::class);

        return view('accounts.index', ['overview' => $overview->for($request->user())]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $data = $request->validated();
        $data['sort_order'] = (int) $request->user()->accounts()->max('sort_order') + 1;
        $account = $request->user()->accounts()->create($data);

        return redirect()->route('accounts.index')->with('status', "Cuenta «{$account->name}» agregada.");
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return redirect()->route('accounts.index')->with('status', "Cuenta «{$account->name}» actualizada.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Cuenta eliminada.');
    }
}
