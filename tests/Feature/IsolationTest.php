<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Envelope;
use App\Models\Income;
use App\Models\Movement;
use App\Models\RecurringRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * Un usuario jamás puede ver ni tocar un id ajeno.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));

    $this->owner = User::factory()->create();
    $this->period = makePeriod($this->owner, ['year' => 2026, 'month' => 10], ['Comida' => '600']);
    $this->envelope = $this->period->envelopes->first();
    $this->card = Commitment::factory()->for($this->owner)->create(['type' => 'debt', 'is_card' => true, 'total_balance' => '500']);
    $this->movement = Movement::factory()->for($this->owner)->for($this->envelope)->create();
    $this->income = Income::factory()->for($this->owner)->for($this->period)->create();
    $this->payment = CommitmentPayment::factory()->for($this->owner)->for($this->card)->for($this->period)->create();
    $this->rule = RecurringRule::factory()->for($this->owner)->create();

    $this->intruder = User::factory()->create();
    $this->intruderPeriod = makePeriod($this->intruder, ['year' => 2026, 'month' => 10], ['Mío' => '100']);

    $this->snapshot = fn () => collect(['envelopes', 'movements', 'incomes', 'commitments', 'commitment_payments', 'recurring_rules'])
        ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('user_id', $this->owner->id)->get()->toJson()]);
    $this->before = ($this->snapshot)();
});

afterEach(function () {
    // Nada del dueño cambió, sin importar lo que intentó el intruso.
    expect(($this->snapshot)()->all())->toBe($this->before->all());
});

it('forbids update and delete on every resource', function () {
    $as = $this->actingAs($this->intruder);

    $as->put(route('envelopes.update', $this->envelope), ['name' => 'x', 'allocated' => '1'])->assertForbidden();
    $as->delete(route('envelopes.destroy', $this->envelope))->assertForbidden();
    $as->put(route('movements.update', $this->movement), ['envelope_id' => $this->intruderPeriod->envelopes->first()->id, 'amount' => '1'])->assertForbidden();
    $as->delete(route('movements.destroy', $this->movement))->assertForbidden();
    $as->put(route('incomes.update', $this->income), ['period_id' => $this->intruderPeriod->id, 'amount' => '1', 'source' => 'x', 'received_at' => '2026-10-01'])->assertForbidden();
    $as->delete(route('incomes.destroy', $this->income))->assertForbidden();
    $as->put(route('commitments.update', $this->card), ['name' => 'x', 'type' => 'fixed', 'monthly_amount' => '1', 'pay_day' => 1])->assertForbidden();
    $as->delete(route('commitments.destroy', $this->card))->assertForbidden();
    $as->put(route('commitment-payments.update', $this->payment), ['commitment_id' => Commitment::factory()->for($this->intruder)->create()->id, 'amount' => '1', 'paid_at' => '2026-10-01'])->assertForbidden();
    $as->delete(route('commitment-payments.destroy', $this->payment))->assertForbidden();
    $as->put(route('recurring-rules.update', $this->rule), ['name' => 'x', 'kind' => 'envelope', 'amount' => '1'])->assertForbidden();
    $as->delete(route('recurring-rules.destroy', $this->rule))->assertForbidden();
    $as->get(route('commitments.show', $this->card))->assertForbidden();
});

it('rejects foreign ids inside payloads', function () {
    $as = $this->actingAs($this->intruder);

    $as->post(route('envelopes.store'), ['period_id' => $this->period->id, 'name' => 'x', 'allocated' => '1'])->assertSessionHasErrors('period_id');
    $as->post(route('movements.store'), ['envelope_id' => $this->envelope->id, 'amount' => '1'])->assertSessionHasErrors('envelope_id');
    $as->post(route('movements.store'), [
        'envelope_id' => $this->intruderPeriod->envelopes->first()->id, 'amount' => '1', 'paid_with_commitment_id' => $this->card->id,
    ])->assertSessionHasErrors('paid_with_commitment_id');
    $as->post(route('incomes.store'), ['period_id' => $this->period->id, 'amount' => '1', 'source' => 'x', 'received_at' => '2026-10-01'])->assertSessionHasErrors('period_id');
    $as->post(route('commitment-payments.store'), ['commitment_id' => $this->card->id, 'amount' => '1', 'paid_at' => '2026-10-01'])->assertSessionHasErrors('commitment_id');
    $as->post(route('recurring-rules.store'), ['name' => 'x', 'kind' => 'commitment_due', 'commitment_id' => $this->card->id, 'amount' => '1'])->assertSessionHasErrors('commitment_id');
    $as->put(route('settings.update'), ['currency_default' => 'PEN', 'default_card_id' => $this->card->id])->assertSessionHasErrors('default_card_id');
    $as->postJson(route('movements.quick'), ['input' => '5', 'envelope_id' => $this->envelope->id, 'amount' => '5'])->assertStatus(422);
});

it('never shows another user\'s data on screens or exports', function () {
    $this->card->forceFill(['name' => 'Tarjeta secreta'])->saveQuietly();
    $this->before = ($this->snapshot)();
    Envelope::whereKey($this->envelope->id)->update(['name' => 'Sobre secreto']);
    $this->before = ($this->snapshot)();

    $as = $this->actingAs($this->intruder);

    $as->get('/')->assertOk()->assertDontSee('Sobre secreto');
    $as->get(route('periods.setup'))->assertOk()->assertDontSee('Sobre secreto')->assertDontSee('Tarjeta secreta');
    $as->get(route('commitments.index'))->assertOk()->assertDontSee('Tarjeta secreta');
    $as->get(route('history.index'))->assertOk()->assertDontSee('Sobre secreto');
    $as->get(route('settings'))->assertOk()->assertDontSee('Tarjeta secreta');
    $as->getJson(route('charts.data', 'envelopes'))->assertJsonMissing(['Sobre secreto']);
    expect($as->get(route('export'))->getContent())->not->toContain('secreto')
        ->and($as->get(route('export', ['format' => 'csv']))->getContent())->not->toContain('secreto');
});

it('blocks deleting an envelope that has movements', function () {
    $this->actingAs($this->owner)->delete(route('envelopes.destroy', $this->envelope))->assertSessionHasErrors('envelopes');
});
