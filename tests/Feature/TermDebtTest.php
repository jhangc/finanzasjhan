<?php

use App\Models\Commitment;
use App\Models\User;
use App\Services\Debts\CommitmentOverview;
use App\Services\Debts\DebtProgress;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(10, 0));
    $this->user = User::factory()->create();
    makePeriod($this->user, ['year' => 2026, 'month' => 10]);
});

it('creates an apartment debt with term, defaulting the balance to the total', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Departamento', 'type' => 'debt', 'is_card' => '0',
        'original_amount' => '36000', 'term_months' => '36', 'start_date' => '2026-11-05',
        'monthly_amount' => '1000', 'pay_day' => '5',
    ])->assertSessionHasNoErrors()->assertRedirect(route('commitments.index'));

    $depa = Commitment::first();
    expect($depa->original_amount)->toBe('36000.00')
        ->and($depa->total_balance)->toBe('36000.00')
        ->and($depa->term_months)->toBe(36)
        ->and($depa->start_date->toDateString())->toBe('2026-11-05');
});

it('requires the installment amount and first date when there are installments', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo Yape', 'type' => 'debt', 'is_card' => '0', 'term_months' => '3',
    ])->assertSessionHasErrors(['installment_amount', 'start_date']);

    // Sin cuotas fijas hay que decir cuánto se debe.
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo de mi hermano', 'type' => 'debt', 'is_card' => '0',
    ])->assertSessionHasErrors('total_balance');
});

it('shows the progress of the apartment and lowers it with each payment', function () {
    $depa = Commitment::factory()->for($this->user)->create([
        'name' => 'Departamento', 'type' => 'debt', 'original_amount' => '36000', 'total_balance' => '34000',
        'term_months' => 36, 'start_date' => '2026-09-05', 'monthly_amount' => '1000', 'pay_day' => 5,
    ]);

    $this->actingAs($this->user)->get(route('commitments.show', $depa))
        ->assertOk()
        ->assertSee('Avance de la deuda')
        ->assertSee('S/ 2,000.00')            // pagado
        ->assertSee('S/ 34,000.00')           // te falta
        ->assertSee('Cuotas vencidas hasta hoy')
        ->assertSee('Vas al día');

    // Paga 1,500 este mes (más que el mínimo).
    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $depa->id, 'amount' => '1500', 'paid_at' => '2026-10-26',
    ]);

    expect($depa->fresh()->total_balance)->toBe('32500.00');

    $this->actingAs($this->user)->get(route('commitments.show', $depa))->assertSee('Vas adelantado');
    $this->actingAs($this->user)->get(route('commitments.index'))->assertSee('Cuota 3 de 36');
});

it('records the down payment this month and starts installments next month', function () {
    // Hoy 26/10: pagó la inicial; la primera cuota es el 30/11.
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Departamento', 'type' => 'debt', 'is_card' => '0',
        'original_amount' => '60000', 'down_payment' => '24000', 'down_payment_date' => '2026-10-20',
        'term_months' => '36', 'start_date' => '2026-11-30', 'monthly_amount' => '1000', 'pay_day' => '30',
    ])->assertSessionHasNoErrors();

    $depa = Commitment::first();
    $payment = $depa->payments()->first();

    expect($depa->total_balance)->toBe('36000.00')
        ->and($depa->down_payment)->toBe('24000.00')
        ->and($payment->amount)->toBe('24000.00')
        ->and($payment->note)->toBe('Cuota inicial')
        ->and($payment->paid_at->toDateString())->toBe('2026-10-20')
        ->and($payment->period->month)->toBe(10);

    $progress = app(DebtProgress::class)->for($depa);
    expect($progress['paid'])->toBe('24000.00')
        ->and($progress['financed'])->toBe('36000.00')
        ->and($progress['installments_due'])->toBe(0)
        ->and($progress['status'])->toBe('on_track')
        ->and($progress['end_date']->toDateString())->toBe('2029-10-30');

    // No sale vencido este mes: su primer cobro es el 30/11.
    $overview = app(CommitmentOverview::class)->for($this->user, $this->user->periods()->first());
    $item = $overview['items']->first();
    expect($item['overdue'])->toBeFalse()
        ->and($item['due_date']->toDateString())->toBe('2026-11-30');

    $this->actingAs($this->user)->get(route('commitments.show', $depa))
        ->assertSee('Inicial')
        ->assertSee('S/ 24,000.00')
        ->assertSee('Cuota inicial');
});

it('does not subtract the down payment twice when the remaining balance is typed', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Departamento', 'type' => 'debt', 'is_card' => '0',
        'original_amount' => '60000', 'down_payment' => '24000', 'total_balance' => '35000',
        'term_months' => '36', 'start_date' => '2026-09-30', 'monthly_amount' => '1000', 'pay_day' => '30',
    ])->assertSessionHasNoErrors();

    expect(Commitment::first()->total_balance)->toBe('35000.00')
        ->and(Commitment::first()->payments()->count())->toBe(1);
});

it('rejects a down payment larger than the price', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Departamento', 'type' => 'debt', 'is_card' => '0',
        'original_amount' => '1000', 'down_payment' => '5000',
        'term_months' => '12', 'start_date' => '2026-11-30', 'monthly_amount' => '100', 'pay_day' => '30',
    ])->assertSessionHasErrors('down_payment');
});

it('keeps the balance when editing with the remaining field empty', function () {
    $depa = Commitment::factory()->for($this->user)->create([
        'name' => 'Departamento', 'type' => 'debt', 'original_amount' => '60000', 'down_payment' => '24000',
        'total_balance' => '33000', 'term_months' => 36, 'start_date' => '2026-11-30', 'monthly_amount' => '1000', 'pay_day' => 30,
    ]);

    $this->actingAs($this->user)->put(route('commitments.update', $depa), [
        'name' => 'Depa Los Olivos', 'type' => 'debt', 'is_card' => '0', 'original_amount' => '60000', 'down_payment' => '24000',
        'total_balance' => '', 'term_months' => '36', 'start_date' => '2026-11-30', 'monthly_amount' => '1200', 'pay_day' => '30',
    ])->assertSessionHasNoErrors();

    expect($depa->fresh()->total_balance)->toBe('33000.00')->and($depa->fresh()->monthly_amount)->toBe('1200.00');
});

it('keeps term fields off for cards and monthly payments', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Internet', 'type' => 'fixed', 'is_card' => '0', 'monthly_amount' => '120', 'pay_day' => '15',
        'original_amount' => '999', 'term_months' => '12', 'start_date' => '2026-01-01',
    ])->assertSessionHasNoErrors();

    expect(Commitment::first()->term_months)->toBeNull()->and(Commitment::first()->original_amount)->toBeNull();
});

it('creates a loan from installments: count, amount and period', function () {
    // 3 cuotas mensuales de 200 desde el 28/11: total 600, día de pago 28.
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo Yape', 'type' => 'debt', 'is_card' => '0',
        'term_months' => '3', 'installment_amount' => '200', 'frequency' => 'monthly', 'start_date' => '2026-11-28',
    ])->assertSessionHasNoErrors();

    $loan = Commitment::first();
    expect($loan->original_amount)->toBe('600.00')
        ->and($loan->total_balance)->toBe('600.00')
        ->and($loan->installment_amount)->toBe('200.00')
        ->and($loan->monthly_amount)->toBe('200.00')
        ->and($loan->pay_day)->toBe(28)
        ->and($loan->frequency)->toBe('monthly');

    $p = app(DebtProgress::class)->for($loan);
    expect($p['end_date']->toDateString())->toBe('2027-01-28')
        ->and($p['next_due']->toDateString())->toBe('2026-11-28');
});

it('handles biweekly and weekly installments', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo quincenal', 'type' => 'debt', 'is_card' => '0',
        'term_months' => '4', 'installment_amount' => '150', 'frequency' => 'biweekly', 'start_date' => '2026-10-15',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo semanal', 'type' => 'debt', 'is_card' => '0',
        'term_months' => '8', 'installment_amount' => '60', 'frequency' => 'weekly', 'start_date' => '2026-10-20',
    ])->assertSessionHasNoErrors();

    [$biweekly, $weekly] = Commitment::orderBy('id')->get();

    expect($biweekly->monthly_amount)->toBe('300.00')       // 2 cuotas al mes
        ->and($biweekly->pay_day)->toBeNull()
        ->and($weekly->monthly_amount)->toBe('260.00');       // 60 × 52 / 12

    // Hoy 26/10: la quincenal ya tuvo su 1.ª cuota (15/10); la siguiente es el 30/10.
    $p = app(DebtProgress::class)->for($biweekly);
    expect($p['installments_due'])->toBe(1)
        ->and($p['next_due']->toDateString())->toBe('2026-10-30')
        ->and($p['end_date']->toDateString())->toBe('2026-11-29')
        ->and($p['status'])->toBe('behind');                  // no registró el pago del 15/10

    $w = app(DebtProgress::class)->for($weekly);
    expect($w['installments_due'])->toBe(1)
        ->and($w['next_due']->toDateString())->toBe('2026-10-27')
        ->and($w['end_date']->toDateString())->toBe('2026-12-08');

    $item = app(CommitmentOverview::class)->for($this->user, $this->user->periods()->first())['items']
        ->firstWhere('commitment.id', $biweekly->id);
    expect($item['due_date']->toDateString())->toBe('2026-10-30')->and($item['overdue'])->toBeTrue();
});

it('keeps a down payment and a different total in advanced options', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Departamento', 'type' => 'debt', 'is_card' => '0',
        'term_months' => '36', 'installment_amount' => '1000', 'frequency' => 'monthly', 'start_date' => '2026-11-30',
        'original_amount' => '124172.50', 'down_payment' => '5000', 'down_payment_date' => '2026-10-24',
    ])->assertSessionHasNoErrors();

    $depa = Commitment::first();
    expect($depa->original_amount)->toBe('124172.50')
        ->and($depa->total_balance)->toBe('119172.50')
        ->and($depa->pay_day)->toBe(30);
});
