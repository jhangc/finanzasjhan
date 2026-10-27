<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Envelope;
use App\Models\Income;
use App\Models\Movement;
use App\Models\Period;
use App\Models\RecurringRule;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

dataset('owned models', [
    'period' => Period::class,
    'income' => Income::class,
    'commitment' => Commitment::class,
    'commitment payment' => CommitmentPayment::class,
    'envelope' => Envelope::class,
    'movement' => Movement::class,
    'recurring rule' => RecurringRule::class,
]);

it('lets the owner manage their own record', function (string $model) {
    $owner = User::factory()->create();
    $record = $model::factory()->for($owner)->create();

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($owner->can($ability, $record))->toBeTrue("{$ability} debería permitirse");
    }
})->with('owned models');

it('never lets a user touch another user\'s record', function (string $model) {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $record = $model::factory()->for($owner)->create();

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($intruder->can($ability, $record))->toBeFalse("{$ability} debería negarse");
    }
})->with('owned models');

it('keeps nested records under the same user', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->for($user)->create();

    expect($movement->envelope->user_id)->toBe($user->id)
        ->and($movement->envelope->period->user_id)->toBe($user->id);
});

it('scopes queries to the given user', function () {
    [$a, $b] = User::factory()->count(2)->create();
    Envelope::factory()->for($a)->for(Period::factory()->for($a))->count(2)->create();
    Envelope::factory()->for($b)->create();

    expect(Envelope::ownedBy($a)->count())->toBe(2)
        ->and(Envelope::ownedBy($b)->count())->toBe(1);
});

it('wires up the period relationships', function () {
    $user = User::factory()->create();
    $period = Period::factory()->for($user)->create();
    $envelope = Envelope::factory()->for($user)->for($period)->create();
    Movement::factory()->for($user)->for($envelope)->count(3)->create();

    expect($period->envelopes)->toHaveCount(1)
        ->and($period->movements)->toHaveCount(3)
        ->and($period->movements->first()->amount)->toBe('25.00');
});

it('allows only one period per user and month', function () {
    $user = User::factory()->create();
    Period::factory()->for($user)->create(['year' => 2026, 'month' => 10]);

    Period::factory()->for($user)->create(['year' => 2026, 'month' => 10]);
})->throws(UniqueConstraintViolationException::class);

it('soft deletes movements and commitment payments', function () {
    $movement = Movement::factory()->create();
    $payment = CommitmentPayment::factory()->create();

    $movement->delete();
    $payment->delete();

    expect(Movement::count())->toBe(0)
        ->and(Movement::withTrashed()->count())->toBe(1)
        ->and(CommitmentPayment::withTrashed()->count())->toBe(1);
});
