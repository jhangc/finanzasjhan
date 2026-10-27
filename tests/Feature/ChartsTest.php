<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();

    $this->card = Commitment::factory()->for($this->user)->create([
        'type' => 'debt', 'is_card' => true, 'total_balance' => '1000.00', 'monthly_amount' => '200', 'cut_day' => 20,
    ]);

    $this->september = makePeriod($this->user, ['year' => 2026, 'month' => 9, 'expected_income' => '6000'], ['Comida' => '600']);
    $this->october = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500'],
        ['Comida' => '600', 'Super' => '700']);
});

it('renders the charts page', function () {
    $this->actingAs($this->user)->get(route('charts.index'))->assertOk()->assertSee('chart.js@4.4.1');
});

it('returns spending per envelope', function () {
    Movement::factory()->for($this->user)->for($this->october->envelopes->first())->create(['amount' => '80.50']);

    $this->actingAs($this->user)->getJson(route('charts.data', 'envelopes'))
        ->assertOk()
        ->assertJson(['labels' => ['Comida', 'Super'], 'data' => ['80.50', '0.00']])
        ->assertJsonCount(2, 'colors');
});

it('returns six months of income, spending and debt payments', function () {
    Income::factory()->for($this->user)->for($this->october)->create(['amount' => '7000', 'confirmed' => true]);
    Movement::factory()->for($this->user)->for($this->september->envelopes->first())->create(['amount' => '300']);
    CommitmentPayment::factory()->for($this->user)->for($this->card)->for($this->september)->create(['amount' => '200', 'paid_at' => '2026-09-05']);

    $json = $this->actingAs($this->user)->getJson(route('charts.data', 'monthly'))->assertOk()->json();

    expect($json['labels'])->toBe(['may 26', 'jun 26', 'jul 26', 'ago 26', 'sep 26', 'oct 26'])
        ->and($json['income'])->toBe(['0.00', '0.00', '0.00', '0.00', '6000.00', '7000.00'])
        ->and($json['spent'][4])->toBe('300.00')
        ->and($json['debt'][4])->toBe('200.00');
});

it('rebuilds the debt line backwards from the current balance', function () {
    $envelope = $this->october->envelopes->first();
    // Saldo actual 1000 (incluye un cargo de octubre de 150 y un pago de septiembre de 200).
    Movement::factory()->for($this->user)->for($envelope)->create(['amount' => '150', 'spent_at' => '2026-10-03', 'paid_with_commitment_id' => $this->card->id]);
    CommitmentPayment::factory()->for($this->user)->for($this->card)->for($this->september)->create(['amount' => '200', 'paid_at' => '2026-09-05']);
    $this->card->fresh()->update(['total_balance' => '1000.00']);

    $json = $this->actingAs($this->user)->getJson(route('charts.data', 'debt'))->assertOk()->json();

    // Fin de septiembre: 1000 - 150 = 850. Fin de agosto: 850 + 200 = 1050.
    expect($json['data'])->toBe(['1050.00', '1050.00', '1050.00', '1050.00', '850.00', '1000.00']);
});

it('rejects unknown chart types and other users\' data never leaks', function () {
    $this->actingAs($this->user)->getJson('/charts/data/secret')->assertNotFound();

    $intruder = User::factory()->create();
    $this->actingAs($intruder)->getJson(route('charts.data', 'envelopes'))->assertJson(['labels' => []]);
    $this->actingAs($intruder)->getJson(route('charts.data', 'debt'))->assertJsonPath('data.5', '0.00');
});
