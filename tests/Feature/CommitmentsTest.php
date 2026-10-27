<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Varios' => '500']);
});

it('creates a credit card commitment', function () {
    $response = $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'BCP Visa', 'type' => 'debt', 'total_balance' => '2300', 'monthly_amount' => '450',
        'pay_day' => 5, 'cut_day' => 20, 'credit_limit' => '8000', 'is_card' => '1', 'interest_rate' => '59.990', 'active' => '1',
    ]);

    $card = Commitment::first();
    $response->assertRedirect(route('commitments.index'));

    expect($card->is_card)->toBeTrue()
        ->and($card->total_balance)->toBe('2300.00')
        ->and($card->cut_day)->toBe(20)
        ->and($card->user_id)->toBe($this->user->id);
});

it('drops card fields for non debts and requires balance for debts', function () {
    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Internet', 'type' => 'fixed', 'monthly_amount' => '120', 'pay_day' => 15,
        'is_card' => '1', 'cut_day' => 3, 'total_balance' => '999',
    ])->assertRedirect();

    $internet = Commitment::first();
    expect($internet->is_card)->toBeFalse()->and($internet->cut_day)->toBeNull()->and($internet->total_balance)->toBeNull();

    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Préstamo', 'type' => 'debt', 'monthly_amount' => '300', 'pay_day' => 10,
    ])->assertSessionHasErrors('total_balance');

    $this->actingAs($this->user)->post(route('commitments.store'), [
        'name' => 'Seguro auto', 'type' => 'annual', 'monthly_amount' => '150',
    ])->assertSessionDoesntHaveErrors();
});

it('lists commitments with balance, progress and due badge', function () {
    Commitment::factory()->for($this->user)->create([
        'name' => 'Departamento', 'type' => 'debt', 'total_balance' => '85000', 'monthly_amount' => '1450', 'pay_day' => 13,
    ]);

    $this->actingAs($this->user)->get(route('commitments.index'))
        ->assertOk()
        ->assertSee('Departamento')
        ->assertSee('S/ 85,000.00')
        ->assertSee('59')            // meses restantes
        ->assertSee('Vence en 2 días');
});

it('registers a payment that lowers the debt and can be reverted', function () {
    $depa = Commitment::factory()->for($this->user)->create([
        'type' => 'debt', 'total_balance' => '85000', 'monthly_amount' => '1450', 'pay_day' => 5,
    ]);

    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $depa->id, 'amount' => '1450', 'paid_at' => '2026-10-05',
    ])->assertRedirect();

    $payment = CommitmentPayment::first();
    expect($payment->period_id)->toBe($this->period->id)
        ->and($depa->fresh()->total_balance)->toBe('83550.00');

    $this->actingAs($this->user)->delete(route('commitment-payments.destroy', $payment))->assertRedirect();

    expect($depa->fresh()->total_balance)->toBe('85000.00')
        ->and(CommitmentPayment::withTrashed()->count())->toBe(1);
});

it('creates the month period when paying in a month without one', function () {
    $internet = Commitment::factory()->for($this->user)->create(['monthly_amount' => '120']);

    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $internet->id, 'amount' => '120', 'paid_at' => '2026-11-02',
    ])->assertRedirect();

    expect(Period::where('month', 11)->exists())->toBeTrue();
});

it('shows the card cycle and the amount to avoid interest', function () {
    $card = Commitment::factory()->for($this->user)->create([
        'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '0', 'cut_day' => 20, 'pay_day' => 5,
    ]);
    $envelope = $this->period->envelopes->first();
    Movement::factory()->for($this->user)->for($envelope)->create(['amount' => '150', 'spent_at' => '2026-09-15', 'paid_with_commitment_id' => $card->id]);
    Movement::factory()->for($this->user)->for($envelope)->create(['amount' => '40', 'spent_at' => '2026-10-02', 'paid_with_commitment_id' => $card->id]);

    $this->actingAs($this->user)->get(route('commitments.show', $card))
        ->assertOk()
        ->assertSee('Ciclo de la tarjeta')
        ->assertSee('S/ 150.00')
        ->assertSee('05/10/2026')
        ->assertSee('S/ 40.00');
});

it('updates and deletes a commitment', function () {
    $c = Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'monthly_amount' => '120']);

    $this->actingAs($this->user)->put(route('commitments.update', $c), [
        'name' => 'Internet fibra', 'type' => 'fixed', 'monthly_amount' => '139.90', 'pay_day' => 15, 'active' => '0',
    ])->assertRedirect();

    expect($c->fresh()->name)->toBe('Internet fibra')->and($c->fresh()->active)->toBeFalse();

    $this->actingAs($this->user)->delete(route('commitments.destroy', $c))->assertRedirect(route('commitments.index'));
    expect(Commitment::count())->toBe(0);
});

it('forbids touching another user\'s commitments', function () {
    $foreign = Commitment::factory()->for(User::factory()->create())->create(['type' => 'debt', 'total_balance' => '1000']);

    $this->actingAs($this->user)->get(route('commitments.show', $foreign))->assertForbidden();
    $this->actingAs($this->user)->put(route('commitments.update', $foreign), [
        'name' => 'x', 'type' => 'fixed', 'monthly_amount' => '1', 'pay_day' => 1,
    ])->assertForbidden();
    $this->actingAs($this->user)->delete(route('commitments.destroy', $foreign))->assertForbidden();
    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $foreign->id, 'amount' => '100', 'paid_at' => '2026-10-05',
    ])->assertSessionHasErrors('commitment_id');

    expect($foreign->fresh()->total_balance)->toBe('1000.00');
});
