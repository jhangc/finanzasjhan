<?php

use App\Models\Commitment;
use App\Models\User;
use App\Services\Debts\CommitmentOverview;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();
});

it('starts planning with fixed payments as step 1', function () {
    $this->actingAs($this->user)->get(route('periods.setup'))
        ->assertOk()
        ->assertSeeInOrder(['Paso 1', 'Tus gastos fijos', 'Paso 2', 'Tu ingreso y ahorro', 'Paso 3', 'Tus categorías de gasto'])
        ->assertSee('Agregar gasto fijo')
        ->assertSee('Guardar mes y empezar');
});

it('adds a fixed payment from the planner and returns to it', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'setup', 'name' => 'Internet', 'type' => 'fixed', 'is_card' => '0', 'monthly_amount' => '120', 'pay_day' => '15',
    ])->assertRedirect(route('periods.setup'))->assertSessionHas('status', 'Gasto fijo «Internet» agregado.');

    $this->actingAs($this->user)->get(route('periods.setup'))
        ->assertSee('Internet')
        ->assertSee('S/ 120.00');
});

it('splits an annual payment into twelve months', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'setup', 'name' => 'SOAT', 'type' => 'annual', 'is_card' => '0', 'annual_total' => '1800',
    ])->assertSessionHasNoErrors();

    $soat = Commitment::first();
    expect($soat->monthly_amount)->toBe('150.00')->and($soat->pay_day)->toBeNull();
});

it('creates a credit card from the simple form', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'from' => 'setup', 'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => '1', 'credit_limit' => '8000',
        'monthly_amount' => '450', 'pay_day' => '5', 'total_balance' => '2300', 'cut_day' => '20',
    ])->assertSessionHasNoErrors();

    $card = Commitment::first();
    expect($card->is_card)->toBeTrue()->and($card->cut_day)->toBe(20)->and($card->total_balance)->toBe('2300.00');
});

it('removes a fixed payment from the planner', function () {
    $internet = Commitment::factory()->for($this->user)->create(['name' => 'Internet']);

    $this->actingAs($this->user)->delete(route('commitments.destroy', $internet), ['from' => 'setup'])
        ->assertRedirect(route('periods.setup'));

    expect(Commitment::count())->toBe(0);
});

it('counts a new fixed expense from today onwards', function () {
    // Registrado el 11/10 con día de pago 4: este mes no queda como vencido.
    $casa = Commitment::factory()->for($this->user)->create(['name' => 'casa', 'monthly_amount' => '750', 'pay_day' => 4]);
    // Registrado antes del día 4: sí se vence este mes.
    $old = Commitment::factory()->for($this->user)->create(['name' => 'luz', 'monthly_amount' => '80', 'pay_day' => 4]);
    $old->forceFill(['created_at' => '2026-09-20 10:00:00'])->saveQuietly();
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->get(route('commitments.index'))
        ->assertOk()
        ->assertSee('Desde el 04/11')
        ->assertSee('Vencido hace 7 días')
        ->assertSee('S/ 80.00');

    $overview = app(CommitmentOverview::class)->for($this->user, $this->user->periods()->first());
    $item = $overview['items']->firstWhere('commitment.id', $casa->id);

    expect($item['starts_later'])->toBeTrue()
        ->and($item['overdue'])->toBeFalse()
        ->and($item['due_date']->toDateString())->toBe('2026-11-04')
        ->and($overview['pending'])->toBe('80.00')
        ->and($overview['next']['commitment']->id)->toBe($old->id);
});

it('still counts a fixed expense registered before its pay day', function () {
    Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'monthly_amount' => '120', 'pay_day' => 15]);
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->get(route('commitments.index'))->assertSee('Vence en 4 días');
});

it('uses the new names across the app', function () {
    makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);

    $this->actingAs($this->user)->get('/')
        ->assertSee('Gastos fijos')
        ->assertSee('Planificar')
        ->assertSee('Tus categorías')
        ->assertDontSee('Compromisos')
        ->assertDontSee('Sobres');
});
