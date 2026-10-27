<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\User;
use App\Services\Debts\CardStatement;
use App\Services\Debts\CommitmentOverview;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Hoy 27/10: el último corte fue el 10/10 y se paga hasta el 05/11.
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);
});

function bcpCard($user, array $attributes = []): Commitment
{
    return test()->actingAs($user)->post(route('commitments.store'), $attributes + [
        'from' => 'accounts', 'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => '1',
        'credit_limit' => '9400', 'total_balance' => '9294.72', 'statement_balance' => '4100.50',
        'monthly_amount' => '277.08', 'cut_day' => '10', 'pay_day' => '5',
    ])->assertSessionHasNoErrors() ? Commitment::latest('id')->first() : null;
}

it('computes the last cut and the due date after it', function () {
    expect(CardStatement::lastCut(10, CarbonImmutable::parse('2026-10-27'))->toDateString())->toBe('2026-10-10')
        ->and(CardStatement::lastCut(10, CarbonImmutable::parse('2026-10-10'))->toDateString())->toBe('2026-10-10')
        ->and(CardStatement::lastCut(10, CarbonImmutable::parse('2026-10-09'))->toDateString())->toBe('2026-09-10')
        ->and(CardStatement::dueAfter(CarbonImmutable::parse('2026-10-10'), 5)->toDateString())->toBe('2026-11-05')
        ->and(CardStatement::dueAfter(CarbonImmutable::parse('2026-10-10'), 25)->toDateString())->toBe('2026-10-25');
});

it('separates what you must pay this month from the new charges', function () {
    $card = bcpCard($this->user);

    expect($card->statement_date->toDateString())->toBe('2026-10-10');

    $s = app(CardStatement::class)->for($card);
    expect($s['statement'])->toBe('4100.50')
        ->and($s['pending'])->toBe('4100.50')
        ->and($s['minimum_pending'])->toBe('277.08')
        ->and($s['new_charges'])->toBe('5194.22')   // 9,294.72 − 4,100.50 (la laptop, etc.)
        ->and($s['due_date']->toDateString())->toBe('2026-11-05')
        ->and($s['days_until'])->toBe(9)
        ->and($s['outdated'])->toBeFalse();

    $this->actingAs($this->user)->get(route('accounts.index'))
        ->assertSee('A pagar hasta el 05/11')
        ->assertSee('S/ 4,100.50')
        ->assertSee('Consumos nuevos')
        ->assertSee('S/ 5,194.22');
});

it('subtracts payments made after the cut', function () {
    $card = bcpCard($this->user);

    // Pago anterior al corte: no cuenta para este estado de cuenta.
    CommitmentPayment::factory()->for($this->user)->for($card)->for($this->period)->create(['amount' => '500', 'paid_at' => '2026-10-05']);
    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $card->id, 'amount' => '1000', 'paid_at' => '2026-10-27',
    ]);

    $s = app(CardStatement::class)->for($card->fresh());
    expect($s['paid_since'])->toBe('1000.00')
        ->and($s['pending'])->toBe('3100.50')
        ->and($s['minimum_pending'])->toBe('0.00')
        ->and($s['paid'])->toBeFalse();

    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $card->id, 'amount' => '3100.50', 'paid_at' => '2026-11-02',
    ]);
    expect(app(CardStatement::class)->for($card->fresh())['paid'])->toBeTrue();
});

it('uses the statement for the monthly overview and due alerts', function () {
    $card = bcpCard($this->user);

    $item = app(CommitmentOverview::class)->for($this->user, $this->period)['items']->firstWhere('commitment.id', $card->id);

    expect($item['monthly'])->toBe('4100.50')
        ->and($item['pending'])->toBe('4100.50')
        ->and($item['due_date']->toDateString())->toBe('2026-11-05')
        ->and($item['overdue'])->toBeFalse();
});

it('marks the statement as outdated after a new cut', function () {
    $card = bcpCard($this->user);
    $this->travelTo(now()->setDate(2026, 11, 12));

    expect(app(CardStatement::class)->for($card->fresh())['outdated'])->toBeTrue();
    $this->actingAs($this->user)->get(route('commitments.show', $card))->assertSee('Desactualizado');
});

it('keeps the statement date when editing other fields and renews it with a new amount', function () {
    $card = bcpCard($this->user);
    $this->travelTo(now()->setDate(2026, 11, 12));

    $payload = [
        'name' => 'BCP Visa Oro', 'type' => 'debt', 'is_card' => '1', 'credit_limit' => '9400', 'total_balance' => '9294.72',
        'statement_balance' => '4100.50', 'monthly_amount' => '277.08', 'cut_day' => '10', 'pay_day' => '5',
    ];
    $this->actingAs($this->user)->put(route('commitments.update', $card), $payload)->assertSessionHasNoErrors();
    expect($card->fresh()->statement_date->toDateString())->toBe('2026-10-10');

    $this->actingAs($this->user)->put(route('commitments.update', $card), ['statement_balance' => '5200'] + $payload)->assertSessionHasNoErrors();
    expect($card->fresh()->statement_date->toDateString())->toBe('2026-11-10');
});

it('rejects a statement bigger than the total debt', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'X', 'type' => 'debt', 'is_card' => '1', 'credit_limit' => '1000', 'total_balance' => '100',
        'statement_balance' => '500', 'cut_day' => '10', 'pay_day' => '5',
    ])->assertSessionHasErrors('statement_balance');
});
