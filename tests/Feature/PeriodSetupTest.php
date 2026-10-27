<?php

use App\Models\Commitment;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();
});

it('shows a draft based on the previous month', function () {
    makePeriod($this->user, ['year' => 2026, 'month' => 9, 'expected_income' => '6500.00', 'savings_target' => '800.00'],
        ['Comida fuera' => '600.00', 'Salidas' => '400.00']);
    Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'monthly_amount' => '120.00']);

    $this->actingAs($this->user)->get(route('periods.setup'))
        ->assertOk()
        ->assertSee('Planifica Octubre 2026')
        ->assertSee('6500.00')
        ->assertSee('Comida fuera')
        ->assertSee('Internet')
        ->assertSee('Copiar categorías del mes anterior');

    expect(Period::count())->toBe(1);
});

it('creates the period with its envelopes and closes the previous one', function () {
    $previous = makePeriod($this->user, ['year' => 2026, 'month' => 9]);

    $this->actingAs($this->user)->put(route('periods.save'), [
        'expected_income' => '6500',
        'savings_target' => '800.50',
        'envelopes' => [
            ['name' => 'Comida fuera', 'allocated' => '600', 'color' => '#2563eb'],
            ['name' => 'Supermercado', 'allocated' => '700.25'],
        ],
    ])->assertRedirect(route('dashboard'));

    $period = Period::where('month', 10)->first();
    expect($period->savings_target)->toBe('800.50')
        ->and($period->envelopes->pluck('name')->all())->toBe(['Comida fuera', 'Supermercado'])
        ->and($period->envelopes->last()->allocated)->toBe('700.25')
        ->and($previous->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($this->user)->get('/')->assertOk();
});

it('syncs envelopes: renames, adds and removes', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600', 'Salidas' => '400']);
    [$comida, $salidas] = $period->envelopes;

    $this->actingAs($this->user)->put(route('periods.save'), [
        'expected_income' => '5000', 'savings_target' => '0',
        'envelopes' => [
            ['id' => $comida->id, 'name' => 'Comida fuera', 'allocated' => '650'],
            ['name' => 'Antojos', 'allocated' => '150'],
        ],
    ])->assertRedirect();

    $names = $period->fresh()->envelopes->pluck('name')->all();
    expect($names)->toBe(['Comida fuera', 'Antojos'])
        ->and($salidas->fresh())->toBeNull()
        ->and($comida->fresh()->allocated)->toBe('650.00');
});

it('refuses to remove envelopes that have movements', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600', 'Salidas' => '400']);
    Movement::factory()->for($this->user)->for($period->envelopes->last())->create();

    $this->actingAs($this->user)->put(route('periods.save'), [
        'expected_income' => '5000', 'savings_target' => '0',
        'envelopes' => [['id' => $period->envelopes->first()->id, 'name' => 'Comida', 'allocated' => '600']],
    ])->assertSessionHasErrors('envelopes');

    expect($period->fresh()->envelopes)->toHaveCount(2);
});

it('validates amounts and duplicate names', function () {
    $this->actingAs($this->user)->put(route('periods.save'), [
        'expected_income' => '-1',
        'savings_target' => '10.555',
        'envelopes' => [['name' => 'Comida', 'allocated' => 'abc'], ['name' => 'comida', 'allocated' => '1']],
    ])->assertSessionHasErrors(['expected_income', 'savings_target', 'envelopes.0.allocated', 'envelopes.0.name']);
});

it('cannot touch envelopes from another user through setup', function () {
    $foreign = makePeriod(User::factory()->create(), ['year' => 2026, 'month' => 10], ['Ajeno' => '100']);
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->put(route('periods.save'), [
        'expected_income' => '1000', 'savings_target' => '0',
        'envelopes' => [['id' => $foreign->envelopes->first()->id, 'name' => 'Hack', 'allocated' => '1']],
    ])->assertNotFound();

    expect($foreign->envelopes->first()->fresh()->name)->toBe('Ajeno');
});

it('copies envelopes from the previous month without carrying spending', function () {
    $september = makePeriod($this->user, ['year' => 2026, 'month' => 9, 'expected_income' => '6000'],
        ['Comida' => '600', 'Salidas' => '400']);
    Movement::factory()->for($this->user)->for($september->envelopes->first())->create(['amount' => '100']);
    makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '650']);

    $this->actingAs($this->user)->post(route('periods.copy-previous'))
        ->assertRedirect(route('periods.setup'))
        ->assertSessionHas('status', 'Se copió 1 categoría del mes anterior.');

    $october = Period::where('month', 10)->first();
    expect($october->envelopes->pluck('allocated', 'name')->all())->toBe(['Comida' => '650.00', 'Salidas' => '400.00'])
        ->and($october->movements)->toHaveCount(0);
});

it('stores incomes for the period', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->post(route('incomes.store'), [
        'period_id' => $period->id, 'amount' => '6500', 'source' => 'Sueldo', 'received_at' => '2026-10-01', 'confirmed' => '1',
    ])->assertRedirect();

    expect($period->incomes()->first()->confirmed)->toBeTrue();

    $this->actingAs($this->user)->get(route('periods.setup'))->assertSee('Sueldo');
});
