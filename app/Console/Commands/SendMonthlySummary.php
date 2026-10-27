<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Notifications\MonthlySummary;
use App\Services\Budget\MonthlySummaryBuilder;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Día 1: resumen del mes que acaba de cerrar.
 */
class SendMonthlySummary extends Command
{
    protected $signature = 'fincontrol:monthly-summary {--date= : Fecha de referencia (por defecto hoy)}';

    protected $description = 'Envía el resumen del mes anterior a cada usuario';

    public function handle(MonthlySummaryBuilder $builder): int
    {
        $month = CarbonImmutable::parse($this->option('date') ?? now())->startOfMonth()->subMonthNoOverflow();
        $sent = 0;

        Period::query()->where('year', $month->year)->where('month', $month->month)->with('user')
            ->lazyById()
            ->each(function (Period $period) use ($builder, &$sent) {
                $summary = $builder->for($period);
                $period->user->notify(new MonthlySummary($summary));

                $this->line("→ {$period->user->email}: gastó ".Money::format($summary['spent']).', ahorró '.Money::format($summary['saved']));
                $sent++;
            });

        $this->info("Resúmenes de {$month->format('m/Y')}: {$sent}".(config('fincontrol.mail_notifications') ? '' : ' (correo desactivado)'));

        return self::SUCCESS;
    }
}
