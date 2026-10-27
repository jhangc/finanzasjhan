<?php

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Movement;
use App\Models\Period;
use App\Models\RecurringRule;
use App\Models\User;
use App\Notifications\MonthlySummary;
use App\Notifications\PaymentDueSoon;
use App\Services\Budget\MonthlySummaryBuilder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'jhan@example.com', 'reminder_email' => 'avisos@example.com']);
});

describe('generate monthly period', function () {
    it('closes the previous month and copies envelopes with spending reset', function () {
        $october = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6500', 'savings_target' => '800'],
            ['Comida' => '600', 'Salidas' => '400']);
        Movement::factory()->for($this->user)->for($october->envelopes->first())->create(['amount' => '550']);
        $userWithoutPeriods = User::factory()->create();

        $this->artisan('fincontrol:generate-period', ['--date' => '2026-11-01'])
            ->expectsOutputToContain('Periodos creados para 11/2026: 1')
            ->assertSuccessful();

        $november = Period::ownedBy($this->user)->where('month', 11)->first();
        expect($november->expected_income)->toBe('6500.00')
            ->and($november->savings_target)->toBe('800.00')
            ->and($november->envelopes->pluck('allocated', 'name')->all())->toBe(['Comida' => '600.00', 'Salidas' => '400.00'])
            ->and($november->movements)->toHaveCount(0)
            ->and($october->fresh()->closed_at)->not->toBeNull()
            ->and($userWithoutPeriods->periods()->count())->toBe(0);
    });

    it('is idempotent', function () {
        makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);

        $this->artisan('fincontrol:generate-period', ['--date' => '2026-11-01'])->assertSuccessful();
        $this->artisan('fincontrol:generate-period', ['--date' => '2026-11-01'])->expectsOutputToContain(': 0');

        expect(Period::ownedBy($this->user)->where('month', 11)->first()->envelopes)->toHaveCount(1);
    });

    it('applies recurring rules', function () {
        makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);
        $internet = Commitment::factory()->for($this->user)->create(['monthly_amount' => '120', 'pay_day' => 15]);

        RecurringRule::factory()->for($this->user)->create(['name' => 'Mascota', 'kind' => 'envelope', 'amount' => '150']);
        RecurringRule::factory()->for($this->user)->create(['name' => 'comida', 'kind' => 'envelope', 'amount' => '999']);
        RecurringRule::factory()->for($this->user)->create(['name' => 'Pausada', 'kind' => 'envelope', 'amount' => '1', 'active' => false]);
        RecurringRule::factory()->for($this->user)->create([
            'name' => 'Internet nuevo plan', 'kind' => 'commitment_due', 'commitment_id' => $internet->id, 'amount' => '139.90', 'day_of_month' => 20,
        ]);

        $this->artisan('fincontrol:generate-period', ['--date' => '2026-11-01'])->assertSuccessful();

        $november = Period::ownedBy($this->user)->where('month', 11)->first();
        expect($november->envelopes->pluck('allocated', 'name')->all())->toBe(['Comida' => '600.00', 'Mascota' => '150.00'])
            ->and($internet->fresh()->monthly_amount)->toBe('139.90')
            ->and($internet->fresh()->pay_day)->toBe(20);
    });
});

describe('payment reminders', function () {
    beforeEach(function () {
        config(['fincontrol.mail_notifications' => true]);
        Notification::fake();
        $this->period = makePeriod($this->user, ['year' => 2026, 'month' => 10]);
    });

    it('notifies commitments due in two days without a payment', function () {
        $internet = Commitment::factory()->for($this->user)->create(['name' => 'Internet', 'pay_day' => 15]);
        $depa = Commitment::factory()->for($this->user)->create(['name' => 'Depa', 'pay_day' => 15]);
        Commitment::factory()->for($this->user)->create(['name' => 'Otro día', 'pay_day' => 16]);
        Commitment::factory()->for($this->user)->create(['name' => 'Inactivo', 'pay_day' => 15, 'active' => false]);
        CommitmentPayment::factory()->for($this->user)->for($depa)->for($this->period)->create();

        $this->artisan('fincontrol:payment-reminders', ['--date' => '2026-10-13'])
            ->expectsOutputToContain('Recordatorios: 1')
            ->assertSuccessful();

        Notification::assertSentTo($this->user, PaymentDueSoon::class,
            fn (PaymentDueSoon $n, array $channels, $notifiable) => $n->commitment->is($internet)
                && $n->dueDate->toDateString() === '2026-10-15'
                && $notifiable->routeNotificationFor('mail') === 'avisos@example.com');
        Notification::assertSentTimes(PaymentDueSoon::class, 1);
    });

    it('handles pay days that do not exist in short months', function () {
        Commitment::factory()->for($this->user)->create(['name' => 'Fin de mes', 'pay_day' => 31]);

        $this->artisan('fincontrol:payment-reminders', ['--date' => '2026-02-26'])->assertSuccessful();

        Notification::assertSentTo($this->user, PaymentDueSoon::class,
            fn (PaymentDueSoon $n) => $n->dueDate->toDateString() === '2026-02-28');
    });

    it('sends nothing when mail notifications are disabled', function () {
        config(['fincontrol.mail_notifications' => false]);
        Commitment::factory()->for($this->user)->create(['pay_day' => 15]);

        $this->artisan('fincontrol:payment-reminders', ['--date' => '2026-10-13'])
            ->expectsOutputToContain('(correo desactivado)');

        Notification::assertNothingSent();
    });
});

describe('monthly summary', function () {
    it('summarizes the closed month', function () {
        $october = makePeriod($this->user, ['year' => 2026, 'month' => 10, 'expected_income' => '6000', 'savings_target' => '500'],
            ['Comida' => '600', 'Antojos' => '100']);
        $card = Commitment::factory()->for($this->user)->create(['type' => 'debt', 'is_card' => true, 'total_balance' => '2000', 'monthly_amount' => '450']);
        [$comida, $antojos] = $october->envelopes;
        Movement::factory()->for($this->user)->for($comida)->create(['amount' => '400']);
        Movement::factory()->for($this->user)->for($antojos)->create(['amount' => '130', 'paid_with_commitment_id' => $card->id]);
        CommitmentPayment::factory()->for($this->user)->for($card)->for($october)->create(['amount' => '450']);

        $summary = app(MonthlySummaryBuilder::class)->for($october);

        expect($summary['income'])->toBe('6000.00')
            ->and($summary['spent'])->toBe('530.00')
            ->and($summary['paid'])->toBe('450.00')
            ->and($summary['over_envelopes'])->toBe([['name' => 'Antojos', 'over' => '30.00']])
            ->and($summary['debt_reduction'])->toBe('320.00')
            ->and($summary['saved'])->toBe('5020.00');
    });

    it('sends the summary on day 1 for the previous month', function () {
        config(['fincontrol.mail_notifications' => true]);
        Notification::fake();
        makePeriod($this->user, ['year' => 2026, 'month' => 10]);
        makePeriod($this->user, ['year' => 2026, 'month' => 11]);

        $this->artisan('fincontrol:monthly-summary', ['--date' => '2026-11-01'])
            ->expectsOutputToContain('Resúmenes de 10/2026: 1')
            ->assertSuccessful();

        Notification::assertSentTo($this->user, MonthlySummary::class,
            fn (MonthlySummary $n) => $n->summary['period']->month === 10);
    });

    it('renders the summary email', function () {
        $october = makePeriod($this->user, ['year' => 2026, 'month' => 10], ['Comida' => '600']);
        $mail = (new MonthlySummary(app(MonthlySummaryBuilder::class)->for($october)))->toMail($this->user);

        expect($mail->subject)->toBe('Tu resumen de octubre 2026');
    });
});

it('schedules the tasks', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('fincontrol:generate-period')
        ->expectsOutputToContain('fincontrol:payment-reminders')
        ->expectsOutputToContain('fincontrol:monthly-summary')
        ->expectsOutputToContain('backup:run');
});
