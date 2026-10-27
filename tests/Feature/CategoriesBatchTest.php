<?php

use App\Models\Period;
use App\Models\User;
use App\Services\Budget\PeriodBuilder;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    $this->user = User::factory()->create();
});

it('offers the default categories in a modal on the Anotar screen', function () {
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    $this->actingAs($this->user)->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('Nuevas categorías')
        ->assertSee('Créalas aquí')
        ->assertSee('Gasolina')
        ->assertSee('Apuestas')
        ->assertSee('Ropa')
        ->assertSee('Calzado');
});

it('creates several categories at once and skips existing ones', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);

    $this->actingAs($this->user)->post(route('envelopes.batch'), [
        'redirect_to' => 'transactions',
        'categories' => [
            ['name' => 'comida', 'allocated' => ''],
            ['name' => 'Gasolina', 'allocated' => '300'],
            ['name' => 'Reparación de moto', 'allocated' => ''],
            ['name' => 'Fútbol', 'allocated' => '80.50'],
        ],
    ])->assertRedirect(route('transactions.create'))->assertSessionHas('status', 'Se crearon 3 categorías.');

    $envelopes = $period->fresh()->envelopes;
    expect($envelopes->pluck('name')->all())->toBe(['Comida', 'Gasolina', 'Reparación de moto', 'Fútbol'])
        ->and($envelopes->firstWhere('name', 'Gasolina')->allocated)->toBe('300.00')
        ->and($envelopes->firstWhere('name', 'Reparación de moto')->allocated)->toBe('0.00')
        ->and($envelopes->pluck('color')->filter()->count())->toBe(3);

    // Ya aparecen para anotar gastos.
    $this->actingAs($this->user)->get(route('transactions.create'))->assertSee('Te quedan S/ 300.00');
});

it('creates the month if it does not exist yet', function () {
    $this->actingAs($this->user)->post(route('envelopes.batch'), [
        'categories' => [['name' => 'Ropa'], ['name' => 'Calzado']],
    ])->assertSessionHasNoErrors();

    expect(Period::ownedBy($this->user)->first()->envelopes->pluck('name')->all())->toBe(['Ropa', 'Calzado']);
});

it('validates the categories', function () {
    $this->actingAs($this->user)->post(route('envelopes.batch'), ['categories' => []])->assertSessionHasErrors('categories');
    $this->actingAs($this->user)->post(route('envelopes.batch'), [
        'categories' => [['name' => 'Gasolina'], ['name' => 'gasolina']],
    ])->assertSessionHasErrors('categories.0.name');
});

it('has the requested defaults', function () {
    expect(PeriodBuilder::DEFAULT_CATEGORIES)->toBe(['Comida', 'Reparación de moto', 'Gasolina', 'Apuestas', 'Diversión', 'Fútbol', 'Ropa', 'Calzado']);
});
