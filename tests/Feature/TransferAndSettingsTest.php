<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(10, 0));
    $this->user = User::factory()->create(['usd_rate' => '3.750']);
    $this->card = Commitment::factory()->for($this->user)->create([
        'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '2300', 'monthly_amount' => '450', 'cut_day' => 20,
    ]);
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500'], ['Comida' => '600', 'Super' => '700']);
    Movement::factory()->for($this->user)->for($this->period->envelopes->first())
        ->create(['amount' => '45.50', 'note' => 'menú, "doble"', 'spent_at' => '2026-10-02 13:00:00', 'paid_with_commitment_id' => $this->card->id]);
    CommitmentPayment::factory()->for($this->user)->for($this->card)->for($this->period)->create(['amount' => '450', 'paid_at' => '2026-10-05']);
});

it('exports the full backup as JSON', function () {
    $response = $this->actingAs($this->user)->get(route('export', ['format' => 'json']))->assertOk();

    $json = json_decode($response->getContent(), true);
    expect($response->headers->get('content-disposition'))->toContain('fincontrol-respaldo-')
        ->and($json['app'])->toBe('FinControl')
        ->and($json['commitments'][0]['name'])->toBe('BCP Visa')
        ->and($json['periods'][0]['envelopes'][0]['movements'][0]['amount'])->toBe('45.50')
        ->and($json['periods'][0]['payments'][0]['amount'])->toBe('450.00');
});

it('exports movements as CSV', function () {
    $csv = $this->actingAs($this->user)->get(route('export', ['format' => 'csv']))->assertOk()->getContent();

    expect($csv)->toContain('fecha,categoria,monto,nota,tarjeta')
        ->and($csv)->toContain('"2026-10-02 13:00:00",Comida,45.50,"menú, ""doble""","BCP Visa"');
});

it('imports a JSON backup into another account without double counting balances', function () {
    $backup = $this->actingAs($this->user)->get(route('export'))->getContent();
    $newUser = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('respaldo.json', $backup);
    $this->actingAs($newUser)->post(route('import'), ['file' => $file])->assertRedirect()->assertSessionHas('status');

    $card = Commitment::ownedBy($newUser)->first();
    expect($card->total_balance)->toBe('1895.50') // 2300 + 45.50 - 450, tal cual estaba
        ->and(Movement::ownedBy($newUser)->first()->paid_with_commitment_id)->toBe($card->id)
        ->and($newUser->fresh()->usd_rate)->toBe('3.750');

    // Importar de nuevo no duplica nada.
    $again = UploadedFile::fake()->createWithContent('respaldo.json', $backup);
    $this->actingAs($newUser)->post(route('import'), ['file' => $again])->assertSessionHas('status', 'Importación lista: no había nada nuevo.');
    expect(Movement::ownedBy($newUser)->count())->toBe(1)
        ->and(CommitmentPayment::ownedBy($newUser)->count())->toBe(1)
        ->and($card->fresh()->total_balance)->toBe('1895.50');
});

it('imports a CSV of movements', function () {
    $csv = "fecha;sobre;monto;nota;tarjeta\n2026-10-03 09:00;Comida;12,50;café;\n2026-10-04;Taxi;18;;bcp visa\n";
    $file = UploadedFile::fake()->createWithContent('movs.csv', $csv);

    $this->actingAs($this->user)->post(route('import'), ['file' => $file])
        ->assertSessionHas('status', 'Importación lista: 2 gastos, 1 categorías.');

    expect($this->period->fresh()->envelopes->pluck('name')->all())->toContain('Taxi')
        ->and($this->card->fresh()->total_balance)->toBe('1913.50'); // 1895.50 + 18
});

it('rejects invalid import files', function () {
    $this->actingAs($this->user)->post(route('import'), ['file' => UploadedFile::fake()->createWithContent('x.json', '{"foo":1}')])
        ->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('import'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "a,b\n1,2")])
        ->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('import'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "fecha,sobre,monto\nayer,Comida,-5")])
        ->assertSessionHasErrors('file');

    expect(Movement::count())->toBe(1);
});

it('updates settings and only accepts own cards', function () {
    $this->actingAs($this->user)->get(route('settings'))->assertOk()->assertSee('BCP Visa');

    $this->actingAs($this->user)->put(route('settings.update'), [
        'currency_default' => 'PEN', 'usd_rate' => '3.712', 'default_card_id' => $this->card->id, 'reminder_email' => 'avisos@example.com',
    ])->assertSessionHasNoErrors();

    expect($this->user->fresh()->default_card_id)->toBe($this->card->id)
        ->and($this->user->fresh()->usd_rate)->toBe('3.712');

    $foreignCard = Commitment::factory()->for(User::factory()->create())->create(['is_card' => true]);
    $this->actingAs($this->user)->put(route('settings.update'), [
        'currency_default' => 'PEN', 'default_card_id' => $foreignCard->id,
    ])->assertSessionHasErrors('default_card_id');
});

it('manages recurring rules', function () {
    $this->actingAs($this->user)->post(route('recurring-rules.store'), [
        'name' => 'Mascota', 'kind' => 'envelope', 'amount' => '120',
    ])->assertSessionHasNoErrors();

    $rule = $this->user->recurringRules()->first();
    expect($rule->active)->toBeTrue()->and($rule->commitment_id)->toBeNull();

    $this->actingAs($this->user)->put(route('recurring-rules.update', $rule), [
        'name' => 'Mascota', 'kind' => 'envelope', 'amount' => '150', 'active' => '0',
    ])->assertSessionHasNoErrors();
    expect($rule->fresh()->active)->toBeFalse();

    $this->actingAs(User::factory()->create())->delete(route('recurring-rules.destroy', $rule))->assertForbidden();
    $this->actingAs($this->user)->delete(route('recurring-rules.destroy', $rule))->assertRedirect();
    expect($this->user->recurringRules()->count())->toBe(0);
});
