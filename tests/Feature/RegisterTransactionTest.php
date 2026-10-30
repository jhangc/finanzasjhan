<?php

use App\Models\Account;
use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Income;
use App\Models\Movement;
use App\Models\User;
use App\Services\Budget\CascadeCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600', 'Transporte' => '250']);
    $this->comida = $this->period->envelopes->firstWhere('name', 'Comida');
    $this->bcp = Account::factory()->for($this->user)->create(['name' => 'BCP Sueldo', 'balance' => '3000.00']);
    $this->cash = Account::factory()->for($this->user)->create(['name' => 'Efectivo', 'type' => 'cash', 'balance' => '200.00']);
    $this->visa = Commitment::factory()->for($this->user)->create([
        'name' => 'BCP Visa', 'type' => 'debt', 'is_card' => true, 'total_balance' => '1000', 'credit_limit' => '5000', 'cut_day' => 10, 'pay_day' => 5,
    ]);
    $this->internet = Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'type' => 'fixed', 'monthly_amount' => '120', 'pay_day' => 28]);
    $this->yape = Commitment::factory()->for($this->user)->create([
        'name' => 'Préstamo Yape', 'type' => 'debt', 'total_balance' => '600', 'monthly_amount' => '200', 'pay_day' => 28,
    ]);
});

function register($test, array $data)
{
    return $test->actingAs($test->user)->post(route('transactions.store'), $data + ['kind' => 'expense', 'date' => '2026-10-27']);
}

it('shows the screen with what you can pay and with what', function () {
    $this->actingAs($this->user)->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('¿Qué estás pagando?')
        ->assertSee('¿Con qué pagas?')
        ->assertSee('Comida')
        ->assertSee('Internet')
        ->assertSee('Préstamo Yape')
        ->assertSee('BCP Visa')
        ->assertSee('BCP Sueldo')
        ->assertSee('Saldo S/ 3,000.00');
});

it('records a category expense paid from a debit account', function () {
    register($this, ['amount' => '25.50', 'target' => "envelope:{$this->comida->id}", 'source' => "account:{$this->bcp->id}", 'note' => 'menú'])
        ->assertRedirect(route('transactions.create', ['kind' => 'expense']))
        ->assertSessionHas('status', '✓ Gasto de S/ 25.50 en Comida desde BCP Sueldo.');

    $movement = Movement::first();
    expect($movement->account_id)->toBe($this->bcp->id)
        ->and($this->bcp->fresh()->balance)->toBe('2974.50');

    // Borrarlo devuelve el dinero a la cuenta.
    $movement->delete();
    expect($this->bcp->fresh()->balance)->toBe('3000.00');
});

it('records a category expense with a credit card', function () {
    register($this, ['amount' => '120', 'target' => "envelope:{$this->comida->id}", 'source' => "card:{$this->visa->id}"])
        ->assertSessionHasNoErrors();

    expect($this->visa->fresh()->total_balance)->toBe('1120.00')
        ->and($this->bcp->fresh()->balance)->toBe('3000.00');
});

it('pays a fixed expense from an account and a loan installment with vouchers', function () {
    register($this, ['amount' => '120', 'target' => "commitment:{$this->internet->id}", 'source' => "account:{$this->bcp->id}"])
        ->assertSessionHas('status', '✓ Pago de S/ 120.00 a Internet desde BCP Sueldo.');

    register($this, [
        'amount' => '200', 'target' => "commitment:{$this->yape->id}", 'source' => "account:{$this->cash->id}",
        'vouchers' => [UploadedFile::fake()->image('yape.jpg')],
    ])->assertSessionHasNoErrors();

    expect($this->bcp->fresh()->balance)->toBe('2880.00')
        ->and($this->cash->fresh()->balance)->toBe('0.00')
        ->and($this->yape->fresh()->total_balance)->toBe('400.00')
        ->and(CommitmentPayment::where('commitment_id', $this->yape->id)->first()->vouchers)->toHaveCount(1);
});

it('pays a fixed expense with a credit card', function () {
    register($this, ['amount' => '120', 'target' => "commitment:{$this->internet->id}", 'source' => "card:{$this->visa->id}"])
        ->assertSessionHasNoErrors();

    $payment = CommitmentPayment::first();
    expect($payment->paid_with_commitment_id)->toBe($this->visa->id)
        ->and($this->visa->fresh()->total_balance)->toBe('1120.00');

    $payment->delete();
    expect($this->visa->fresh()->total_balance)->toBe('1000.00');
});

it('pays the credit card from an account but not with another card', function () {
    register($this, ['amount' => '500', 'target' => "commitment:{$this->visa->id}", 'source' => "account:{$this->bcp->id}"])
        ->assertSessionHasNoErrors();

    expect($this->visa->fresh()->total_balance)->toBe('500.00')
        ->and($this->bcp->fresh()->balance)->toBe('2500.00');

    register($this, ['amount' => '100', 'target' => "commitment:{$this->visa->id}", 'source' => "card:{$this->visa->id}"])
        ->assertSessionHasErrors('source');
});

it('allows an expense without discounting any account', function () {
    register($this, ['amount' => '15', 'target' => "envelope:{$this->comida->id}", 'source' => 'none'])->assertSessionHasNoErrors();

    expect(Movement::first()->account_id)->toBeNull()->and($this->bcp->fresh()->balance)->toBe('3000.00');
});

it('records an income into an account', function () {
    $this->actingAs($this->user)->post(route('transactions.store'), [
        'kind' => 'income', 'amount' => '4500', 'date' => '2026-10-27', 'income_source' => 'Sueldo', 'income_account' => $this->bcp->id,
    ])->assertSessionHas('status', '✓ Ingreso de S/ 4,500.00 en BCP Sueldo.');

    $income = Income::first();
    expect($income->confirmed)->toBeTrue()
        ->and($income->period_id)->toBe($this->period->id)
        ->and($this->bcp->fresh()->balance)->toBe('7500.00');

    $income->delete();
    expect($this->bcp->fresh()->balance)->toBe('3000.00');
});

it('records a movement between accounts or from a card without counting it as income', function () {
    $income = fn (array $data) => $this->actingAs($this->user)->post(route('transactions.store'), $data + [
        'kind' => 'income', 'amount' => '300', 'date' => '2026-10-27', 'income_source' => 'Transferencia', 'income_account' => $this->bcp->id,
    ]);

    $income(['income_from' => "account:{$this->cash->id}", 'income_source' => ''])
        ->assertSessionHas('status', '✓ Movimiento de S/ 300.00 desde Efectivo a BCP Sueldo.');
    expect(Income::first()->source)->toBe('Movimiento entre cuentas')
        ->and(Income::earned()->count())->toBe(0)
        ->and(app(CascadeCalculator::class)->for($this->period->fresh())->incomeIsConfirmed)->toBeFalse();
    expect($this->bcp->fresh()->balance)->toBe('3300.00')
        ->and($this->cash->fresh()->balance)->toBe('-100.00');

    $income(['income_from' => "card:{$this->visa->id}"])->assertSessionHasNoErrors();
    expect($this->bcp->fresh()->balance)->toBe('3600.00')
        ->and($this->visa->fresh()->total_balance)->toBe('1300.00');

    Income::latest('id')->first()->delete();
    expect($this->bcp->fresh()->balance)->toBe('3300.00')
        ->and($this->visa->fresh()->total_balance)->toBe('1000.00');

    $income(['income_from' => "account:{$this->bcp->id}"])->assertSessionHasErrors('income_from');
    $foreign = Account::factory()->for(User::factory()->create())->create();
    $income(['income_from' => "account:{$foreign->id}"])->assertSessionHasErrors('income_from');
});

it('validates the selections', function () {
    register($this, ['amount' => '10'])->assertSessionHasErrors(['target', 'source']);

    // Categoría de octubre con fecha de noviembre.
    register($this, ['amount' => '10', 'target' => "envelope:{$this->comida->id}", 'source' => 'none', 'date' => '2026-11-02'])
        ->assertSessionHasErrors('date');

    $other = User::factory()->create();
    $foreignAccount = Account::factory()->for($other)->create();
    $foreignLoan = Commitment::factory()->for($other)->create(['type' => 'debt', 'total_balance' => '100']);

    register($this, ['amount' => '10', 'target' => "envelope:{$this->comida->id}", 'source' => "account:{$foreignAccount->id}"])
        ->assertSessionHasErrors('source');
    register($this, ['amount' => '10', 'target' => "commitment:{$foreignLoan->id}", 'source' => 'none'])
        ->assertSessionHasErrors('target');

    expect($foreignAccount->fresh()->balance)->toBe('1500.00')
        ->and($foreignLoan->fresh()->total_balance)->toBe('100.00')
        ->and(Movement::count() + CommitmentPayment::count())->toBe(0);
});

it('shows where the money came from in the history', function () {
    register($this, ['amount' => '25', 'target' => "envelope:{$this->comida->id}", 'source' => "account:{$this->cash->id}"]);
    register($this, ['amount' => '120', 'target' => "commitment:{$this->internet->id}", 'source' => "card:{$this->visa->id}"]);

    $this->actingAs($this->user)->get(route('history.index'))
        ->assertSee('desde Efectivo')
        ->assertSee('con BCP Visa');
});

it('records a card payment from the commitment screen with the account it comes from', function () {
    $pay = fn (array $data) => $this->actingAs($this->user)->post(route('commitment-payments.store'), $data + [
        'commitment_id' => $this->visa->id, 'amount' => '170', 'paid_at' => '2026-10-28',
    ]);

    $pay(['source' => "account:{$this->bcp->id}"])->assertSessionHasNoErrors();
    $payment = CommitmentPayment::first();
    expect($payment->account_id)->toBe($this->bcp->id)
        ->and($this->bcp->fresh()->balance)->toBe('2830.00')
        ->and($this->visa->fresh()->total_balance)->toBe('830.00');

    $this->actingAs($this->user)->get(route('commitments.show', $this->visa))->assertSee('Desde BCP Sueldo');

    // Editar sin enviar el origen lo mantiene; cambiarlo mueve el saldo de cuenta.
    $update = fn (array $data) => $this->actingAs($this->user)->put(route('commitment-payments.update', $payment), $data + [
        'commitment_id' => $this->visa->id, 'amount' => '170', 'paid_at' => '2026-10-28',
    ]);
    $update(['note' => 'recarga'])->assertSessionHasNoErrors();
    expect($payment->fresh()->account_id)->toBe($this->bcp->id);

    $update(['source' => "account:{$this->cash->id}"])->assertSessionHasNoErrors();
    expect($this->bcp->fresh()->balance)->toBe('3000.00')
        ->and($this->cash->fresh()->balance)->toBe('30.00');

    $pay(['source' => "card:{$this->visa->id}"])->assertSessionHasErrors('source');
    $foreign = Account::factory()->for(User::factory()->create())->create();
    $pay(['source' => "account:{$foreign->id}"])->assertSessionHasErrors('source');
});
