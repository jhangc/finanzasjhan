<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\User;
use App\Services\Budget\TodayState;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();
});

it('redirects to setup when the current month has no period', function () {
    $this->actingAs($this->user)->get('/')->assertRedirect(route('periods.setup'));
});

it('shows how much you can spend today', function () {
    makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500', 'savings_target' => '800'],
        ['Comida fuera' => '600.00', 'Supermercado' => '700.00']);
    Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'monthly_amount' => '120.00', 'pay_day' => 13]);

    $this->actingAs($this->user)->get('/')
        ->assertOk()
        ->assertSee('Puedes gastar hoy')
        ->assertSee('S/ 61.90') // 1,300 / 21 días
        ->assertSee('Comida fuera')
        ->assertSee('Internet');
});

describe('quick entry', function () {
    beforeEach(function () {
        $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10],
            ['Comida fuera' => '600.00', 'Supermercado' => '700.00', 'Varios' => '300.00']);
    });

    it('creates a movement and returns the fresh state', function () {
        $response = $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '25.50 comida']);

        $response->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('movement.envelope', 'Comida fuera')
            ->assertJsonPath('movement.amount', '25.50')
            ->assertJsonPath('state.cascade.spent', '25.50');

        expect(Movement::where('user_id', $this->user->id)->count())->toBe(1);
    });

    it('asks to choose an envelope when nothing matches, then creates it', function () {
        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '40 gasolina'])
            ->assertOk()
            ->assertJsonPath('status', 'choose')
            ->assertJsonPath('amount', '40.00')
            ->assertJsonPath('note', 'gasolina')
            ->assertJsonCount(3, 'envelopes');

        $varios = $this->period->envelopes->firstWhere('name', 'Varios');

        $this->actingAs($this->user)->postJson(route('movements.quick'), [
            'input' => '40 gasolina', 'envelope_id' => $varios->id, 'amount' => '40.00', 'note' => 'gasolina',
        ])->assertCreated()->assertJsonPath('movement.envelope', 'Varios');

        expect(Movement::first()->note)->toBe('gasolina');
    });

    it('rejects input without an amount', function () {
        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => 'comida'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid');

        expect(Movement::count())->toBe(0);
    });

    it('charges the default card when the input ends with tc', function () {
        $card = Commitment::factory()->for($this->user)->create([
            'type' => 'debt', 'is_card' => true, 'total_balance' => '2300.00', 'cut_day' => 20, 'pay_day' => 5,
        ]);
        $this->user->update(['default_card_id' => $card->id]);

        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '120 super tc'])
            ->assertCreated()
            ->assertJsonPath('movement.paid_with_commitment_id', $card->id);

        expect($card->fresh()->total_balance)->toBe('2420.00');
    });

    it('reads a pasted Yape notification', function () {
        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => 'Yapeaste S/ 15.00 a Bodega Rosa'])
            ->assertOk()
            ->assertJsonPath('status', 'choose')
            ->assertJsonPath('amount', '15.00')
            ->assertJsonPath('note', 'Bodega Rosa');
    });

    it('never accepts another user\'s envelope', function () {
        $other = makePeriod(User::factory()->create(), ['year' => 2026, 'month' => 10], ['Ajeno' => '100.00']);

        $this->actingAs($this->user)->postJson(route('movements.quick'), [
            'input' => '10 x', 'envelope_id' => $other->envelopes->first()->id, 'amount' => '10',
        ])->assertStatus(422)->assertJsonValidationErrors('envelope_id');

        expect(Movement::count())->toBe(0);
    });

    it('validates the amount range', function () {
        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '1000000 comida'])
            ->assertStatus(422);
    });

    it('is rate limited', function () {
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '1 varios'])->assertCreated();
        }

        $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '1 varios'])->assertStatus(429);
    });
});

it('rejects quick entry when there is no period', function () {
    $this->actingAs($this->user)->postJson(route('movements.quick'), ['input' => '25 comida'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Primero planifica tu mes.');
});

it('lists fixed payments among the latest expenses and in the chart total', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);
    $internet = Commitment::factory()->for($this->user)->create(['name' => 'Internet Ariana', 'monthly_amount' => '31.90', 'pay_day' => 26]);
    CommitmentPayment::factory()->for($this->user)->for($internet)->for($period)->create(['amount' => '31.90', 'paid_at' => '2026-10-10']);
    Movement::factory()->for($this->user)->for($period->envelopes->first())->create(['amount' => '12.00', 'note' => 'menú', 'spent_at' => '2026-10-11 09:00']);

    $state = app(TodayState::class)->for($period->fresh());

    expect(collect($state['recent'])->pluck('note')->all())->toBe(['menú', 'Internet Ariana'])
        ->and($state['recent'][1]['envelope'])->toBe('Gasto fijo')
        ->and($state['cascade']['paidThisMonth'])->toBe('31.90');

    $this->actingAs($this->user)->get('/')->assertSee('Gastos de categorías y pagos fijos del mes');
});
