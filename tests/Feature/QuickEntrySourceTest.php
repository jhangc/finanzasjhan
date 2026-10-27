<?php

use App\Models\Account;
use App\Models\Commitment;
use App\Models\Movement;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600', 'Gasolina' => '300']);
    $this->personal = Account::factory()->for($this->user)->create(['name' => 'Cuenta personal', 'bank' => 'BCP', 'balance' => '1000.00']);
    $this->cash = Account::factory()->for($this->user)->create(['name' => 'Efectivo', 'bank' => null, 'type' => 'cash', 'balance' => '200.00']);
    $this->visa = Commitment::factory()->for($this->user)->create([
        'name' => 'Interbank Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '500', 'credit_limit' => '3000', 'cut_day' => 10,
    ]);
});

function quick($test, array $data)
{
    return $test->actingAs($test->user)->postJson(route('movements.quick'), $data);
}

it('discounts the account chosen in "Pagas con"', function () {
    quick($this, ['input' => '25 comida', 'source' => "account:{$this->personal->id}"])
        ->assertCreated()
        ->assertJsonPath('message', 'Registrado S/ 25.00 en Comida desde Cuenta personal');

    expect(Movement::first()->account_id)->toBe($this->personal->id)
        ->and($this->personal->fresh()->balance)->toBe('975.00');
});

it('uses a card chosen in "Pagas con"', function () {
    quick($this, ['input' => '40 gasolina', 'source' => "card:{$this->visa->id}"])->assertCreated();

    expect($this->visa->fresh()->total_balance)->toBe('540.00')
        ->and($this->personal->fresh()->balance)->toBe('1000.00');
});

it('reads the account from the last word of the text', function () {
    quick($this, ['input' => '25 comida efectivo', 'source' => "account:{$this->personal->id}"])
        ->assertCreated()
        ->assertJsonPath('message', 'Registrado S/ 25.00 en Comida desde Efectivo');

    quick($this, ['input' => '40 gasolina bcp'])->assertCreated();
    quick($this, ['input' => '15 comida visa'])->assertCreated();

    expect($this->cash->fresh()->balance)->toBe('175.00')
        ->and($this->personal->fresh()->balance)->toBe('960.00')
        ->and($this->visa->fresh()->total_balance)->toBe('515.00')
        ->and(Movement::pluck('note')->filter()->all())->toBe([]);
});

it('falls back to the default account', function () {
    $this->user->update(['default_account_id' => $this->cash->id]);

    quick($this, ['input' => '10 comida'])->assertCreated();

    expect($this->cash->fresh()->balance)->toBe('190.00');
});

it('keeps the source when choosing the category with buttons', function () {
    quick($this, ['input' => '30 moto efectivo'])
        ->assertOk()
        ->assertJsonPath('status', 'choose')
        ->assertJsonPath('source', "account:{$this->cash->id}");

    quick($this, [
        'input' => '30 moto efectivo', 'envelope_id' => $this->period->envelopes->firstWhere('name', 'Gasolina')->id,
        'amount' => '30', 'source' => "account:{$this->cash->id}",
    ])->assertCreated();

    expect($this->cash->fresh()->balance)->toBe('170.00');
});

it('records nothing against accounts with "Otro" and ignores foreign accounts', function () {
    quick($this, ['input' => '20 comida', 'source' => 'none'])->assertCreated();

    $foreign = Account::factory()->for(User::factory()->create())->create(['balance' => '50.00']);
    quick($this, ['input' => '20 comida', 'source' => "account:{$foreign->id}"])->assertCreated();

    expect($foreign->fresh()->balance)->toBe('50.00')
        ->and($this->personal->fresh()->balance)->toBe('1000.00')
        ->and(Movement::whereNotNull('account_id')->count())->toBe(0);
});

it('shows "Pagas con" on the dashboard and the default account in settings', function () {
    $this->actingAs($this->user)->get('/')->assertSee('Pagas con:')->assertSee('Cuenta personal');

    $this->actingAs($this->user)->put(route('settings.update'), [
        'currency_default' => 'PEN', 'default_account_id' => $this->personal->id,
    ])->assertSessionHasNoErrors();
    expect($this->user->fresh()->default_account_id)->toBe($this->personal->id);

    $foreign = Account::factory()->for(User::factory()->create())->create();
    $this->actingAs($this->user)->put(route('settings.update'), [
        'currency_default' => 'PEN', 'default_account_id' => $foreign->id,
    ])->assertSessionHasErrors('default_account_id');
});
