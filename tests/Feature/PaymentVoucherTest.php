<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Services\Debts\VoucherStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(10, 0));
    $this->user = User::factory()->create();
    $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);
    $this->depa = Commitment::factory()->for($this->user)->create([
        'name' => 'Departamento', 'type' => 'debt', 'total_balance' => '36000', 'monthly_amount' => '1000', 'pay_day' => 30,
    ]);
});

it('stores several vouchers with a new payment and only shows them to the owner', function () {
    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $this->depa->id, 'amount' => '1000', 'paid_at' => '2026-10-26', 'note' => 'Op. 12345',
        'vouchers' => [
            UploadedFile::fake()->image('transferencia.jpg', 600, 900),
            UploadedFile::fake()->create('constancia.pdf', 200, 'application/pdf'),
        ],
    ])->assertSessionHasNoErrors();

    $payment = CommitmentPayment::first();
    $vouchers = $payment->vouchers;

    expect($vouchers)->toHaveCount(2)
        ->and($vouchers->pluck('original_name')->all())->toBe(['transferencia.jpg', 'constancia.pdf'])
        ->and($vouchers->last()->isPdf())->toBeTrue()
        ->and($this->depa->fresh()->total_balance)->toBe('35000.00');
    $vouchers->each(fn ($v) => Storage::disk('local')->assertExists($v->path));

    $this->actingAs($this->user)->get(route('vouchers.show', $vouchers->first()))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('vouchers.show', $vouchers->first()))->assertForbidden();

    $this->actingAs($this->user)->get(route('commitments.show', $this->depa))
        ->assertSee(route('vouchers.show', $vouchers->first()))
        ->assertSee('+1');
    $this->actingAs($this->user)->get(route('history.index'))->assertSee('2 vouchers');
});

it('edits a payment adding and removing vouchers and recalculating the balance', function () {
    $payment = CommitmentPayment::factory()->for($this->user)->for($this->depa)->for($this->period)
        ->create(['amount' => '1000', 'paid_at' => '2026-10-26']);
    app(VoucherStorage::class)->add($payment, [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]);
    [$a, $b] = $payment->vouchers;

    $this->actingAs($this->user)->put(route('commitment-payments.update', $payment), [
        'commitment_id' => $this->depa->id, 'amount' => '1500', 'paid_at' => '2026-10-25', 'note' => 'Pagué más',
        'remove_vouchers' => [$a->id],
        'vouchers' => [UploadedFile::fake()->create('c.pdf', 100, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->amount)->toBe('1500.00')
        ->and($payment->vouchers->pluck('original_name')->all())->toBe(['b.jpg', 'c.pdf'])
        ->and($this->depa->fresh()->total_balance)->toBe('34500.00');
    Storage::disk('local')->assertMissing($a->path);
    Storage::disk('local')->assertExists($b->path);
});

it('limits vouchers per payment', function () {
    $files = array_map(fn ($i) => UploadedFile::fake()->image("v{$i}.jpg"), range(1, 11));

    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $this->depa->id, 'amount' => '1000', 'paid_at' => '2026-10-26', 'vouchers' => $files,
    ])->assertSessionHasErrors('vouchers');

    $payment = CommitmentPayment::factory()->for($this->user)->for($this->depa)->for($this->period)->create();
    app(VoucherStorage::class)->add($payment, array_slice($files, 0, 9));

    $this->actingAs($this->user)->put(route('commitment-payments.update', $payment), [
        'commitment_id' => $this->depa->id, 'amount' => $payment->amount, 'paid_at' => '2026-10-26',
        'vouchers' => array_slice($files, 9, 2),
    ])->assertSessionHasErrors('vouchers');

    expect($payment->vouchers()->count())->toBe(9);
});

it('rejects files that are not images or PDFs', function () {
    $this->actingAs($this->user)->post(route('commitment-payments.store'), [
        'commitment_id' => $this->depa->id, 'amount' => '1000', 'paid_at' => '2026-10-26',
        'vouchers' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ])->assertSessionHasErrors('vouchers.0');

    expect(CommitmentPayment::count())->toBe(0);
});

it('cannot remove vouchers of another payment or user', function () {
    $mine = CommitmentPayment::factory()->for($this->user)->for($this->depa)->for($this->period)->create();
    $other = CommitmentPayment::factory()->for($this->user)->for($this->depa)->for($this->period)->create();
    app(VoucherStorage::class)->add($other, [UploadedFile::fake()->image('x.jpg')]);

    // Pide quitar un voucher que no es de este pago: se ignora.
    $this->actingAs($this->user)->put(route('commitment-payments.update', $mine), [
        'commitment_id' => $this->depa->id, 'amount' => $mine->amount, 'paid_at' => '2026-10-26',
        'remove_vouchers' => [$other->vouchers->first()->id],
    ])->assertSessionHasNoErrors();
    expect(PaymentVoucher::count())->toBe(1);

    $intruder = User::factory()->create();
    $this->actingAs($intruder)->put(route('commitment-payments.update', $other), [
        'commitment_id' => Commitment::factory()->for($intruder)->create()->id, 'amount' => '1', 'paid_at' => '2026-10-26',
        'remove_vouchers' => [$other->vouchers->first()->id],
    ])->assertForbidden();
    expect(PaymentVoucher::count())->toBe(1);
});

it('migrated the single voucher column into the new table', function () {
    expect(Schema::hasColumn('commitment_payments', 'voucher_path'))->toBeFalse()
        ->and(Schema::hasTable('payment_vouchers'))->toBeTrue();
});
