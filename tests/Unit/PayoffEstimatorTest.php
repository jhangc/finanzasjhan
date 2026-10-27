<?php

use App\Models\Commitment;
use App\Services\Debts\PayoffEstimator;

function debt(array $attributes): Commitment
{
    return new Commitment(['type' => 'debt'] + $attributes);
}

it('estimates months as ceil(balance / monthly)', function () {
    $estimator = new PayoffEstimator;

    expect($estimator->estimate(debt(['total_balance' => '85000.00', 'monthly_amount' => '1450.00']))['months'])->toBe(59)
        ->and($estimator->estimate(debt(['total_balance' => '2300.00', 'monthly_amount' => '450.00']))['months'])->toBe(6)
        ->and($estimator->estimate(debt(['total_balance' => '900.00', 'monthly_amount' => '450.00']))['months'])->toBe(2);
});

it('estimates a longer payoff with simple monthly interest', function () {
    $result = (new PayoffEstimator)->estimate(debt([
        'total_balance' => '2300.00', 'monthly_amount' => '450.00', 'interest_rate' => '60.000',
    ]));

    expect($result['months'])->toBe(6)
        ->and($result['months_with_interest'])->toBe(7);

    $slow = (new PayoffEstimator)->estimate(debt([
        'total_balance' => '10000.00', 'monthly_amount' => '500.00', 'interest_rate' => '24.000',
    ]));

    expect($slow['months'])->toBe(20)
        ->and($slow['months_with_interest'])->toBeGreaterThan(20);
});

it('returns null when the payment never covers the interest', function () {
    $result = (new PayoffEstimator)->estimate(debt([
        'total_balance' => '100000.00', 'monthly_amount' => '100.00', 'interest_rate' => '30.000',
    ]));

    expect($result['months_with_interest'])->toBeNull();
});

it('handles paid off debts and non debts', function () {
    $estimator = new PayoffEstimator;

    expect($estimator->estimate(debt(['total_balance' => '0', 'monthly_amount' => '450.00']))['months'])->toBe(0)
        ->and($estimator->estimate(new Commitment(['type' => 'fixed', 'monthly_amount' => '120.00']))['months'])->toBeNull()
        ->and($estimator->estimate(debt(['total_balance' => '500.00', 'monthly_amount' => '0']))['months'])->toBeNull();
});
