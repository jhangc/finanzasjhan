<?php

use App\Models\Commitment;
use App\Services\Debts\DebtProgress;
use Carbon\CarbonImmutable;

function apartment(array $attributes = []): Commitment
{
    return new Commitment($attributes + [
        'type' => 'debt', 'is_card' => false,
        'original_amount' => '36000.00', 'total_balance' => '36000.00', 'term_months' => 36,
        'monthly_amount' => '1000.00', 'pay_day' => 5, 'start_date' => '2026-08-05',
    ]);
}

function progressOn(Commitment $c, string $today): array
{
    return (new DebtProgress)->for($c, CarbonImmutable::parse($today));
}

it('ignores commitments without a term', function () {
    expect((new DebtProgress)->for(new Commitment(['type' => 'fixed'])))->toBeNull()
        ->and((new DebtProgress)->for(apartment(['is_card' => true])))->toBeNull()
        ->and((new DebtProgress)->for(apartment(['term_months' => null])))->toBeNull();
});

it('tracks installments, paid amount and end date', function () {
    // 3 cuotas vencidas (5 ago, 5 sep, 5 oct) y 3,000 pagados.
    $p = progressOn(apartment(['total_balance' => '33000.00']), '2026-10-26');

    expect($p['paid'])->toBe('3000.00')
        ->and($p['remaining'])->toBe('33000.00')
        ->and($p['percent'])->toBe(8)
        ->and($p['installments_due'])->toBe(3)
        ->and($p['current_installment'])->toBe(4)
        ->and($p['months_left'])->toBe(33)
        ->and($p['first_due']->toDateString())->toBe('2026-08-05')
        ->and($p['end_date']->toDateString())->toBe('2029-07-05')
        ->and($p['status'])->toBe('on_track')
        ->and($p['required_monthly'])->toBe('1000.00');
});

it('says when you are ahead or behind the minimum', function () {
    $ahead = progressOn(apartment(['total_balance' => '31500.00']), '2026-10-26');
    expect($ahead['status'])->toBe('ahead')->and($ahead['difference'])->toBe('1500.00');

    $behind = progressOn(apartment(['total_balance' => '35000.00']), '2026-10-26');
    expect($behind['status'])->toBe('behind')
        ->and($behind['difference'])->toBe('-2000.00')
        ->and($behind['required_monthly'])->toBe('1060.61'); // 35,000 / 33 meses
});

it('projects the balance left for a mortgage when paying only the minimum', function () {
    // Precio 124,172.50, inicial 5,000, 36 cuotas de 1,000 desde el 30/11/2026.
    $depa = apartment([
        'original_amount' => '124172.50', 'down_payment' => '5000.00', 'total_balance' => '119172.50',
        'start_date' => '2026-11-30', 'pay_day' => 30,
    ]);

    $p = progressOn($depa, '2026-10-26');
    expect($p['status'])->toBe('on_track')
        ->and($p['needs_balloon'])->toBeTrue()
        ->and($p['projected_final'])->toBe('83172.50')      // 119,172.50 - 36 × 1,000
        ->and($p['required_monthly'])->toBe('3310.35');      // para no necesitar hipoteca

    // Pagando solo el mínimo un año después sigue al día; amortizar extra baja la proyección.
    $onTrack = progressOn(apartment([...$depa->getAttributes(), 'total_balance' => '107172.50']), '2027-10-30');
    expect($onTrack['status'])->toBe('on_track')->and($onTrack['projected_final'])->toBe('83172.50');

    $extra = progressOn(apartment([...$depa->getAttributes(), 'total_balance' => '97172.50']), '2027-10-30');
    expect($extra['status'])->toBe('ahead')
        ->and($extra['extra_paid'])->toBe('10000.00')
        ->and($extra['projected_final'])->toBe('73172.50');
});

it('needs no mortgage when the minimum covers the balance', function () {
    // 36,000 en 36 cuotas de 1,000, antes de la primera cuota.
    $p = progressOn(apartment(), '2026-08-01');

    expect($p['needs_balloon'])->toBeFalse()->and($p['projected_final'])->toBe('0.00');
});

it('counts the installment due today', function () {
    expect(progressOn(apartment(), '2026-10-04')['installments_due'])->toBe(2)
        ->and(progressOn(apartment(), '2026-10-05')['installments_due'])->toBe(3);
});

it('handles a debt that has not started and one that is finished', function () {
    $future = progressOn(apartment(['start_date' => '2026-12-05']), '2026-10-26');
    expect($future['installments_due'])->toBe(0)
        ->and($future['status'])->toBe('on_track')
        ->and($future['current_installment'])->toBe(1);

    $done = progressOn(apartment(['total_balance' => '0.00']), '2026-10-26');
    expect($done['finished'])->toBeTrue()
        ->and($done['status'])->toBe('finished')
        ->and($done['percent'])->toBe(100)
        ->and($done['required_monthly'])->toBe('0.00');
});

it('aligns the first installment to the pay day', function () {
    // Inicia el 20/08 con pago el día 5: la primera cuota es el 5 de septiembre.
    $p = progressOn(apartment(['start_date' => '2026-08-20']), '2026-10-26');

    expect($p['first_due']->toDateString())->toBe('2026-09-05')
        ->and($p['installments_due'])->toBe(2);
});
