<?php

use App\Models\Account;
use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\User;
use App\Services\Accounts\AccountsOverview;
use App\Services\Debts\CommitmentOverview;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    $this->user = User::factory()->create(['usd_rate' => '3.750']);
});

it('adds, updates and deletes debit accounts', function () {
    $this->actingAs($this->user)->post(route('accounts.store'), [
        'name' => 'Cuenta sueldo', 'bank' => 'BCP', 'type' => 'checking', 'currency' => 'PEN', 'balance' => '2,350.50',
    ])->assertRedirect(route('accounts.index'))->assertSessionHasNoErrors();

    $account = Account::first();
    expect($account->balance)->toBe('2350.50')->and($account->user_id)->toBe($this->user->id);

    $this->actingAs($this->user)->put(route('accounts.update', $account), [
        'name' => 'Cuenta sueldo', 'bank' => 'BCP', 'type' => 'checking', 'currency' => 'PEN', 'balance' => '1800',
    ])->assertSessionHasNoErrors();
    expect($account->fresh()->balance)->toBe('1800.00');

    $this->actingAs($this->user)->delete(route('accounts.destroy', $account))->assertRedirect(route('accounts.index'));
    expect(Account::count())->toBe(0);
});

it('adds a credit card with its credit limit from the accounts screen', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'accounts', 'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => '1',
        'total_balance' => '1200', 'credit_limit' => '8000', 'cut_day' => '20', 'monthly_amount' => '300', 'pay_day' => '5',
    ])->assertRedirect(route('accounts.index'))->assertSessionHasNoErrors();

    expect(Commitment::first()->credit_limit)->toBe('8000.00');
});

it('summarizes what you have, what you owe and your available credit', function () {
    Account::factory()->for($this->user)->create(['name' => 'Sueldo', 'balance' => '3000.00']);
    Account::factory()->for($this->user)->create(['name' => 'Ahorros', 'type' => 'savings', 'balance' => '5000.00']);
    Account::factory()->for($this->user)->create(['name' => 'Dólares', 'currency' => 'USD', 'balance' => '100.00']);
    Commitment::factory()->for($this->user)->create(['name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '2000', 'credit_limit' => '8000', 'cut_day' => 20]);
    Commitment::factory()->for($this->user)->create(['name' => 'IBK', 'type' => 'debt', 'is_card' => true, 'total_balance' => '1500', 'credit_limit' => '2000', 'cut_day' => 10]);
    Commitment::factory()->for($this->user)->create(['name' => 'Sin línea', 'type' => 'debt', 'is_card' => true, 'total_balance' => '500', 'cut_day' => 1]);
    Commitment::factory()->for($this->user)->create(['name' => 'Depa Los Olivos', 'type' => 'debt', 'total_balance' => '119000']);

    $o = app(AccountsOverview::class)->for($this->user);

    expect($o['debit_total'])->toBe('8375.00')        // 3000 + 5000 + 100 × 3.75
        ->and($o['card_debt'])->toBe('4000.00')       // solo tarjetas, no el departamento
        ->and($o['credit_limit'])->toBe('10000.00')
        ->and($o['credit_available'])->toBe('6500.00') // 6000 + 500
        ->and($o['credit_usage'])->toBe(35)            // 3500 / 10000
        ->and($o['net'])->toBe('4375.00')
        ->and($o['loan_debt'])->toBe('119000.00')
        ->and($o['net_all'])->toBe('-114625.00');

    $ibk = $o['cards']->firstWhere('commitment.name', 'IBK');
    expect($ibk['usage'])->toBe(75)->and($ibk['available'])->toBe('500.00');

    $this->actingAs($this->user)->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('S/ 8,375.00')
        ->assertSee('S/ 4,000.00')
        ->assertSee('S/ 6,500.00')
        ->assertSee('Usas el 35% de tu línea')
        ->assertSee('Depa Los Olivos')
        ->assertSee('S/ 119,000.00');
});

it('shows USD balances apart when there is no exchange rate', function () {
    $this->user->update(['usd_rate' => null]);
    Account::factory()->for($this->user)->create(['currency' => 'USD', 'balance' => '250.00']);

    $o = app(AccountsOverview::class)->for($this->user);
    expect($o['debit_total'])->toBe('0.00')->and($o['usd_without_rate'])->toBe('250.00');

    $this->actingAs($this->user)->get(route('accounts.index'))->assertSee('$ 250.00');
});

it('flags a card over its limit and allows overdraft balances', function () {
    Commitment::factory()->for($this->user)->create(['type' => 'debt', 'is_card' => true, 'total_balance' => '2100', 'credit_limit' => '2000', 'cut_day' => 5]);
    $this->actingAs($this->user)->post(route('accounts.store'), [
        'name' => 'Corriente', 'type' => 'checking', 'currency' => 'PEN', 'balance' => '-50',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->user)->get(route('accounts.index'))->assertSee('Sobre la línea')->assertSee('-S/ 50.00');
});

it('keeps accounts private', function () {
    $account = Account::factory()->for($this->user)->create(['name' => 'Secreta', 'balance' => '999']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('accounts.index'))->assertDontSee('Secreta');
    $this->actingAs($intruder)->put(route('accounts.update', $account), [
        'name' => 'x', 'type' => 'cash', 'currency' => 'PEN', 'balance' => '0',
    ])->assertForbidden();
    $this->actingAs($intruder)->delete(route('accounts.destroy', $account))->assertForbidden();

    expect($account->fresh()->balance)->toBe('999.00');
});

it('exports and imports accounts and card limits', function () {
    Account::factory()->for($this->user)->create(['name' => 'Ahorros', 'balance' => '700']);
    Commitment::factory()->for($this->user)->create(['name' => 'Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '10', 'credit_limit' => '5000', 'cut_day' => 3]);
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $backup = $this->actingAs($this->user)->get(route('export'))->getContent();
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('import'), [
        'file' => UploadedFile::fake()->createWithContent('b.json', $backup),
    ])->assertSessionHas('status');

    expect(Account::ownedBy($other)->first()->balance)->toBe('700.00')
        ->and(Commitment::ownedBy($other)->first()->credit_limit)->toBe('5000.00');
});

it('lets a card be saved without a minimum payment but requires its credit limit', function () {
    $card = [
        'from' => 'accounts', 'name' => 'BBVA', 'type' => 'debt', 'is_card' => '1',
        'total_balance' => '350', 'cut_day' => '15', 'pay_day' => '5', 'monthly_amount' => '',
    ];

    $this->actingAs($this->user)->post(route('commitments.store'), $card)->assertSessionHasErrors('credit_limit');

    $this->actingAs($this->user)->post(route('commitments.store'), $card + ['credit_limit' => '3000'])
        ->assertSessionHasNoErrors();

    $bbva = Commitment::first();
    expect($bbva->monthly_amount)->toBe('0.00')->and($bbva->credit_limit)->toBe('3000.00');

    // Sin mínimo: cualquier pago del mes lo deja como pagado.
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);
    $overview = app(CommitmentOverview::class);
    expect($overview->for($this->user, $period)['items']->first()['is_paid'])->toBeFalse();

    CommitmentPayment::factory()->for($this->user)->for($bbva)->for($period)->create(['amount' => '350']);
    expect($overview->for($this->user, $period)['items']->first()['is_paid'])->toBeTrue();
});

it('shows the card form without the type selector on the accounts screen', function () {
    $this->actingAs($this->user)->get(route('accounts.index'))
        ->assertSee('Lo que te da el banco')
        ->assertSee('Consumido hasta hoy')
        ->assertDontSee('¿Qué tipo de pago es?');
});

it('adds a Yape loan with installments from the accounts screen', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'accounts', '_form' => 'loan', 'name' => 'Préstamo Yape', 'type' => 'debt', 'is_card' => '0',
        'original_amount' => '1200', 'term_months' => '6', 'start_date' => '2026-11-15',
        'monthly_amount' => '200', 'pay_day' => '15',
    ])->assertRedirect(route('accounts.index'))->assertSessionHasNoErrors();

    $loan = Commitment::first();
    expect($loan->total_balance)->toBe('1200.00')->and($loan->term_months)->toBe(6);

    $o = app(AccountsOverview::class)->for($this->user);
    $item = $o['loans']->first();
    expect($o['loan_debt'])->toBe('1200.00')
        ->and($o['loan_monthly'])->toBe('200.00')
        ->and($item['next_due']->toDateString())->toBe('2026-11-15')
        ->and($item['progress']['term'])->toBe(6);

    $this->actingAs($this->user)->get(route('accounts.index'))
        ->assertSee('Préstamo Yape')
        ->assertSee('1.ª cuota el 15/11/2026 · 6 cuotas');
});

it('accepts an informal loan with just the amount owed', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'accounts', 'name' => 'Préstamo de mi hermano', 'type' => 'debt', 'is_card' => '0',
        'total_balance' => '800', 'monthly_amount' => '100', 'pay_day' => '30',
    ])->assertSessionHasNoErrors();

    $o = app(AccountsOverview::class)->for($this->user);
    expect($o['loan_debt'])->toBe('800.00')->and($o['loans']->first()['progress'])->toBeNull();

    $this->actingAs($this->user)->get(route('accounts.index'))->assertSee('Préstamo de mi hermano');
});

it('lowers the loan when paying it and shows it as paid off', function () {
    $loan = Commitment::factory()->for($this->user)->create([
        'name' => 'Préstamo Yape', 'type' => 'debt', 'total_balance' => '200', 'monthly_amount' => '200', 'pay_day' => 15,
    ]);
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $loan->id, 'amount' => '200', 'paid_at' => '2026-10-27',
    ]);

    $item = app(AccountsOverview::class)->for($this->user)['loans']->first();
    expect($item['remaining'])->toBe('0.00')->and($item['finished'])->toBeTrue()->and($item['next_due'])->toBeNull();
});
