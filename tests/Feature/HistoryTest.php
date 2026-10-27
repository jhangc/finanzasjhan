<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600', 'Super' => '700']);
    $this->card = Commitment::factory()->for($this->user)->create([
        'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '1000', 'cut_day' => 20,
    ]);

    $this->movement = Movement::factory()->for($this->user)->for($this->period->envelopes->first())
        ->create(['amount' => '45.00', 'note' => 'menú', 'paid_with_commitment_id' => $this->card->id]);
    Income::factory()->for($this->user)->for($this->period)->create(['source' => 'Sueldo', 'amount' => '6500', 'confirmed' => true]);
    CommitmentPayment::factory()->for($this->user)->for($this->card)->for($this->period)->create(['amount' => '300']);
});

it('lists movements, incomes and payments together', function () {
    $this->actingAs($this->user)->get(route('history.index'))
        ->assertOk()
        ->assertSee('menú')
        ->assertSee('Sueldo')
        ->assertSee('Pago: BCP Visa');
});

it('filters by type', function () {
    $this->actingAs($this->user)->get(route('history.index', ['type' => 'income']))
        ->assertOk()
        ->assertSee('Sueldo')
        ->assertDontSee('menú')
        ->assertDontSee('Pago: BCP Visa');
});

it('edits a movement and moves the card charge', function () {
    $super = $this->period->envelopes->last();

    $this->actingAs($this->user)->put(route('movements.update', $this->movement), [
        'envelope_id' => $super->id, 'amount' => '50.00', 'spent_at' => '2026-10-10T12:30', 'note' => 'mercado',
        'paid_with_commitment_id' => '',
    ])->assertRedirect();

    $movement = $this->movement->fresh();
    expect($movement->envelope_id)->toBe($super->id)
        ->and($movement->amount)->toBe('50.00')
        ->and($movement->paid_with_commitment_id)->toBeNull()
        ->and($this->card->fresh()->total_balance)->toBe('700.00'); // 1000 + 45 - 300 - 45
});

it('does not move a movement to another month\'s envelope', function () {
    $september = makePeriod($this->user, ['year' => 2026, 'month' => 9], ['Viejo' => '100']);

    $this->actingAs($this->user)->put(route('movements.update', $this->movement), [
        'envelope_id' => $september->envelopes->first()->id, 'amount' => '45',
    ])->assertSessionHasErrors('envelope_id');
});

it('deletes a movement reverting the card balance', function () {
    expect($this->card->fresh()->total_balance)->toBe('745.00');

    $this->actingAs($this->user)->delete(route('movements.destroy', $this->movement))->assertRedirect();

    expect($this->card->fresh()->total_balance)->toBe('700.00')
        ->and(Movement::withTrashed()->find($this->movement->id)->trashed())->toBeTrue();
});

it('forbids editing or deleting another user\'s records', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->delete(route('movements.destroy', $this->movement))->assertForbidden();
    $this->actingAs($intruder)->put(route('incomes.update', Income::first()), [
        'period_id' => $this->period->id, 'amount' => '1', 'source' => 'x', 'received_at' => '2026-10-01',
    ])->assertSessionHasErrors('period_id');
    $this->actingAs($intruder)->delete(route('commitment-payments.destroy', CommitmentPayment::first()))->assertForbidden();
    $this->actingAs($intruder)->get(route('history.index', ['period' => $this->period->id]))->assertNotFound();

    expect(Movement::count())->toBe(1);
});
