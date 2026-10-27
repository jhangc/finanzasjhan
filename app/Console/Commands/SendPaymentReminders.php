<?php

namespace App\Console\Commands;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Notifications\PaymentDueSoon;
use App\Services\Debts\DueDates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Diario: avisa de los compromisos que vencen en 2 días y no tienen pago en su mes.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'fincontrol:payment-reminders {--date= : Fecha de referencia (por defecto hoy)}';

    protected $description = 'Envía recordatorios de pago a los usuarios';

    public function handle(): int
    {
        $today = CarbonImmutable::parse($this->option('date') ?? now())->startOfDay();
        $daysBefore = (int) config('fincontrol.reminder_days_before');
        $target = $today->addDays($daysBefore);
        $sent = 0;

        Commitment::query()->active()->whereNotNull('pay_day')->with('user')
            ->lazyById()
            ->filter(fn (Commitment $c) => DueDates::inMonth($c->pay_day, $target)->isSameDay($target))
            ->each(function (Commitment $commitment) use ($target, $daysBefore, &$sent) {
                $paid = CommitmentPayment::where('commitment_id', $commitment->id)
                    ->whereHas('period', fn ($q) => $q->where('year', $target->year)->where('month', $target->month))
                    ->exists();

                if ($paid) {
                    return;
                }

                $notification = new PaymentDueSoon($commitment, $target, $daysBefore);
                $commitment->user->notify($notification);
                $this->line("→ {$commitment->user->email}: {$notification->text()}");
                $sent++;
            });

        $this->info("Recordatorios: {$sent}".(config('fincontrol.mail_notifications') ? '' : ' (correo desactivado)'));

        return self::SUCCESS;
    }
}
