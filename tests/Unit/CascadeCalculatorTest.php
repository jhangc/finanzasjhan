<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\User;
use App\Services\Budget\CascadeCalculator;
use App\Services\Budget\DailyAllowance;
use App\Services\Budget\EnvelopeStatus;
use Carbon\CarbonImmutable;

function cascade($period, string $today = '2026-10-11')
{
    return app(CascadeCalculator::class)->for($period, CarbonImmutable::parse($today));
}

function spend($period, string $envelopeName, string $amount, array $extra = []): Movement
{
    $envelope = $period->envelopes()->where('name', $envelopeName)->firstOrFail();

    return Movement::factory()->for($period->user)->for($envelope)->create(['amount' => $amount] + $extra);
}

beforeEach(function () {
    $this->user = User::factory()->create();

    // 2,120 en compromisos activos: 1,450 + 450 + 120 + 100 (150 inactivo no cuenta).
    foreach ([['Departamento', '1450.00', true], ['BCP Visa', '450.00', true], ['Internet', '120.00', true],
        ['Seguro auto', '100.00', true], ['Gimnasio', '150.00', false]] as [$name, $amount, $active]) {
        Commitment::factory()->for($this->user)->create(['name' => $name, 'monthly_amount' => $amount, 'active' => $active]);
    }
});

it('uses the expected income when there are no confirmed incomes', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500.00', 'savings_target' => '800.00'],
        ['Comida' => '600.00', 'Super' => '700.00']);

    Income::factory()->for($this->user)->for($period)->create(['amount' => '9999.00', 'confirmed' => false]);

    $r = cascade($period);

    expect($r->income)->toBe('6500.00')
        ->and($r->incomeIsConfirmed)->toBeFalse()
        ->and($r->savings)->toBe('800.00')
        ->and($r->commitments)->toBe('2120.00')
        ->and($r->availableForEnvelopes)->toBe('3580.00')
        ->and($r->allocated)->toBe('1300.00')
        ->and($r->overAllocated)->toBe('0.00')
        ->and($r->requiredIncome)->toBe('4220.00')
        ->and($r->incomeGap)->toBe('0.00');
});

it('uses confirmed incomes when they exist, converting USD', function () {
    $this->user->update(['usd_rate' => '3.750']);
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500.00', 'savings_target' => '0']);

    Income::factory()->for($this->user)->for($period)->create(['amount' => '3000.00', 'confirmed' => true]);
    Income::factory()->for($this->user)->for($period)->create(['amount' => '100.00', 'currency' => 'USD', 'confirmed' => true]);
    Income::factory()->for($this->user)->for($period)->create(['amount' => '5000.00', 'confirmed' => false]);

    $r = cascade($period->fresh());

    expect($r->income)->toBe('3375.00')
        ->and($r->incomeIsConfirmed)->toBeTrue()
        ->and($r->availableForEnvelopes)->toBe('1255.00');
});

it('computes spent, remaining and the allowance for today', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500.00', 'savings_target' => '800.00'],
        ['Comida' => '600.00', 'Super' => '700.00']);

    spend($period, 'Comida', '25.50');
    spend($period, 'Comida', '74.50');
    spend($period, 'Super', '300.00');
    spend($period, 'Super', '50.00')->delete(); // borrado suave: no cuenta

    // 11 de octubre: quedan 21 días incluyendo hoy.
    $r = cascade($period, '2026-10-11');

    expect($r->spent)->toBe('400.00')
        ->and($r->remaining)->toBe('900.00')
        ->and($r->daysLeft)->toBe(21)
        ->and($r->todayAllowance)->toBe('42.86');

    [$comida, $super] = $r->envelopes;
    expect($comida->spent)->toBe('100.00')
        ->and($comida->remaining)->toBe('500.00')
        ->and($comida->percent)->toBe(16)
        ->and($comida->state)->toBe(EnvelopeStatus::STATE_OK)
        ->and($super->remaining)->toBe('400.00');
});

it('marks an envelope as over when it goes negative without blocking', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Antojos' => '150.00', 'Salidas' => '400.00']);

    spend($period, 'Antojos', '200.00');
    spend($period, 'Salidas', '330.00');

    $r = cascade($period);
    [$antojos, $salidas] = $r->envelopes;

    expect($antojos->state)->toBe(EnvelopeStatus::STATE_OVER)
        ->and($antojos->remaining)->toBe('-50.00')
        ->and($antojos->percent)->toBe(133)
        ->and($salidas->state)->toBe(EnvelopeStatus::STATE_WARNING)
        ->and($r->remaining)->toBe('20.00');
});

it('allows a negative allowance when everything is overspent', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Varios' => '100.00']);
    spend($period, 'Varios', '160.00');

    $r = cascade($period, '2026-10-31');

    expect($r->daysLeft)->toBe(1)
        ->and($r->todayAllowance)->toBe('-60.00');
});

it('gives the whole remaining on the last day of the month', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 2], ['Varios' => '300.00']);
    spend($period, 'Varios', '120.00');

    $r = cascade($period, '2026-02-28');

    expect($r->daysLeft)->toBe(1)
        ->and($r->todayAllowance)->toBe('180.00');
});

it('handles an empty period', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '0', 'savings_target' => '0']);

    $r = cascade($period);

    expect($r->envelopes)->toBe([])
        ->and($r->allocated)->toBe('0.00')
        ->and($r->spent)->toBe('0.00')
        ->and($r->todayAllowance)->toBe('0.00')
        ->and($r->availableForEnvelopes)->toBe('-2120.00')
        ->and($r->requiredIncome)->toBe('2120.00')
        ->and($r->incomeGap)->toBe('2120.00');
});

it('flags over allocation and income gap', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '4000.00', 'savings_target' => '500.00'],
        ['Comida' => '1000.00', 'Super' => '800.00']);

    $r = cascade($period);

    // disponible = 4000 - 500 - 2120 = 1380; asignado 1800.
    expect($r->availableForEnvelopes)->toBe('1380.00')
        ->and($r->overAllocated)->toBe('420.00')
        ->and($r->requiredIncome)->toBe('4420.00')
        ->and($r->incomeGap)->toBe('420.00');
});

it('tracks payments of the month against commitments', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);
    $depa = Commitment::where('name', 'Departamento')->first();

    CommitmentPayment::factory()->for($this->user)->for($depa)->for($period)->create(['amount' => '1450.00']);

    $r = cascade($period);

    expect($r->paidThisMonth)->toBe('1450.00')
        ->and($r->pendingDue)->toBe('670.00');
});

it('ignores data from other users', function () {
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600.00']);

    $other = User::factory()->create();
    Commitment::factory()->for($other)->create(['monthly_amount' => '5000.00']);
    $otherPeriod = makePeriod($other, ['year' => 2026, 'month' => 10], ['Comida' => '600.00']);
    spend($otherPeriod, 'Comida', '599.00');

    $r = cascade($period);

    expect($r->commitments)->toBe('2120.00')
        ->and($r->spent)->toBe('0.00');
});

it('counts days left for past and future periods', function () {
    $daily = new DailyAllowance;
    $period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);

    expect($daily->daysLeft($period, CarbonImmutable::parse('2026-10-01')))->toBe(31)
        ->and($daily->daysLeft($period, CarbonImmutable::parse('2026-10-31 23:59')))->toBe(1)
        ->and($daily->daysLeft($period, CarbonImmutable::parse('2026-09-15')))->toBe(31)
        ->and($daily->daysLeft($period, CarbonImmutable::parse('2026-11-02')))->toBe(1);
});
