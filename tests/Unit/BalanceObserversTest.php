<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->card = Commitment::factory()->for($this->user)->create([
        'type' => 'debt', 'is_card' => true, 'total_balance' => '2300.00', 'monthly_amount' => '450.00', 'cut_day' => 20,
    ]);
    $this->period = makePeriod($this->user, [], ['Varios' => '300.00']);
    $this->envelope = $this->period->envelopes->first();
});

function balance(Commitment $commitment): string
{
    return $commitment->fresh()->total_balance;
}

it('raises the card balance on a card movement and reverts on delete and restore', function () {
    $movement = Movement::factory()->for($this->user)->for($this->envelope)
        ->create(['amount' => '120.50', 'paid_with_commitment_id' => $this->card->id]);

    expect(balance($this->card))->toBe('2420.50');

    $movement->delete();
    expect(balance($this->card))->toBe('2300.00');

    $movement->restore();
    expect(balance($this->card))->toBe('2420.50');

    $movement->forceDelete();
    expect(balance($this->card))->toBe('2300.00');
});

it('does not revert twice when force deleting a trashed movement', function () {
    $movement = Movement::factory()->for($this->user)->for($this->envelope)
        ->create(['amount' => '100.00', 'paid_with_commitment_id' => $this->card->id]);

    $movement->delete();
    $movement->forceDelete();

    expect(balance($this->card))->toBe('2300.00');
});

it('moves the charge when a movement is edited', function () {
    $other = Commitment::factory()->for($this->user)->create(['type' => 'debt', 'is_card' => true, 'total_balance' => '0']);
    $movement = Movement::factory()->for($this->user)->for($this->envelope)
        ->create(['amount' => '100.00', 'paid_with_commitment_id' => $this->card->id]);

    $movement->update(['amount' => '80.00']);
    expect(balance($this->card))->toBe('2380.00');

    $movement->update(['paid_with_commitment_id' => $other->id]);
    expect(balance($this->card))->toBe('2300.00')->and(balance($other))->toBe('80.00');

    $movement->update(['paid_with_commitment_id' => null]);
    expect(balance($other))->toBe('0.00');
});

it('leaves balances alone for cash movements', function () {
    Movement::factory()->for($this->user)->for($this->envelope)->create(['amount' => '50.00']);

    expect(balance($this->card))->toBe('2300.00');
});

it('lowers a debt balance on payment and reverts on delete', function () {
    $payment = CommitmentPayment::factory()->for($this->user)->for($this->card)->for($this->period)
        ->create(['amount' => '450.00']);

    expect(balance($this->card))->toBe('1850.00');

    $payment->update(['amount' => '500.00']);
    expect(balance($this->card))->toBe('1800.00');

    $payment->delete();
    expect(balance($this->card))->toBe('2300.00');
});

it('does not touch balances for fixed commitments', function () {
    $internet = Commitment::factory()->for($this->user)->create(['type' => 'fixed', 'total_balance' => null]);

    CommitmentPayment::factory()->for($this->user)->for($internet)->for($this->period)->create(['amount' => '120.00']);

    expect($internet->fresh()->total_balance)->toBeNull();
});
