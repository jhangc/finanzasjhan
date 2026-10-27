<?php

use App\Models\Commitment;
use App\Models\Movement;
use App\Models\User;
use App\Services\Debts\CardCycleCalculator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->card = Commitment::factory()->for($this->user)->create([
        'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true,
        'total_balance' => '0', 'monthly_amount' => '450.00', 'cut_day' => 20, 'pay_day' => 5,
    ]);
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Varios' => '1000.00']);
    $this->envelope = $this->period->envelopes->first();
});

function charge($test, string $date, string $amount): void
{
    Movement::factory()->for($test->user)->for($test->envelope)->create([
        'amount' => $amount, 'spent_at' => $date, 'paid_with_commitment_id' => $test->card->id,
    ]);
}

it('computes the cycle when today is before the cut day', function () {
    $cycle = app(CardCycleCalculator::class)->for($this->card, CarbonImmutable::parse('2026-10-11'));

    expect($cycle->currentStart->toDateString())->toBe('2026-09-21')
        ->and($cycle->currentEnd->toDateString())->toBe('2026-10-20')
        ->and($cycle->closedStart->toDateString())->toBe('2026-08-21')
        ->and($cycle->closedEnd->toDateString())->toBe('2026-09-20')
        ->and($cycle->paymentDueDate->toDateString())->toBe('2026-10-05');
});

it('computes the cycle when today is after the cut day', function () {
    $cycle = app(CardCycleCalculator::class)->for($this->card, CarbonImmutable::parse('2026-10-25'));

    expect($cycle->currentStart->toDateString())->toBe('2026-10-21')
        ->and($cycle->currentEnd->toDateString())->toBe('2026-11-20')
        ->and($cycle->closedEnd->toDateString())->toBe('2026-10-20')
        ->and($cycle->paymentDueDate->toDateString())->toBe('2026-11-05');
});

it('includes the cut day itself in the current cycle', function () {
    $cycle = app(CardCycleCalculator::class)->for($this->card, CarbonImmutable::parse('2026-10-20'));

    expect($cycle->currentEnd->toDateString())->toBe('2026-10-20');
});

it('sums charges per cycle and the amount to avoid interest', function () {
    charge($this, '2026-09-10 12:00', '100.00');  // ciclo cerrado
    charge($this, '2026-09-20 23:30', '50.00');   // día de corte: ciclo cerrado
    charge($this, '2026-09-21 08:00', '80.00');   // ciclo actual
    charge($this, '2026-10-05 19:00', '20.50');   // ciclo actual
    charge($this, '2026-08-20 10:00', '999.00');  // ciclo anterior al cerrado

    $cycle = app(CardCycleCalculator::class)->for($this->card, CarbonImmutable::parse('2026-10-11'));

    expect($cycle->amountToAvoidInterest)->toBe('150.00')
        ->and($cycle->currentSpent)->toBe('100.50');
});

it('ignores charges on other cards', function () {
    $other = Commitment::factory()->for($this->user)->create(['type' => 'debt', 'is_card' => true, 'cut_day' => 20]);
    Movement::factory()->for($this->user)->for($this->envelope)->create([
        'amount' => '70.00', 'spent_at' => '2026-10-01', 'paid_with_commitment_id' => $other->id,
    ]);

    $cycle = app(CardCycleCalculator::class)->for($this->card, CarbonImmutable::parse('2026-10-11'));

    expect($cycle->currentSpent)->toBe('0.00');
});

it('clamps the cut day in short months', function () {
    $this->card->update(['cut_day' => 31, 'pay_day' => 15]);

    $cycle = app(CardCycleCalculator::class)->for($this->card->fresh(), CarbonImmutable::parse('2026-02-10'));

    expect($cycle->currentEnd->toDateString())->toBe('2026-02-28')
        ->and($cycle->currentStart->toDateString())->toBe('2026-02-01')
        ->and($cycle->closedEnd->toDateString())->toBe('2026-01-31')
        ->and($cycle->paymentDueDate->toDateString())->toBe('2026-02-15');
});

it('rejects commitments that are not cards', function () {
    $fixed = Commitment::factory()->for($this->user)->create(['is_card' => false]);

    app(CardCycleCalculator::class)->for($fixed);
})->throws(InvalidArgumentException::class);
