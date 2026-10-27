<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Budget\PeriodBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Día 1: cierra el periodo anterior y crea el nuevo copiando sobres y aplicando reglas.
 */
class GenerateMonthlyPeriod extends Command
{
    protected $signature = 'fincontrol:generate-period {--date= : Fecha de referencia (por defecto hoy)}';

    protected $description = 'Crea el periodo del mes para cada usuario que ya tiene meses anteriores';

    public function handle(PeriodBuilder $periods): int
    {
        $date = CarbonImmutable::parse($this->option('date') ?? now());
        $created = 0;

        User::query()->whereHas('periods')->lazyById()->each(function (User $user) use ($periods, $date, &$created) {
            $exists = $user->periods()->where('year', $date->year)->where('month', $date->month)->exists();

            if (! $exists) {
                $periods->generate($user, $date->year, $date->month);
                $created++;
            }
        });

        $this->info("Periodos creados para {$date->format('m/Y')}: {$created}");

        return self::SUCCESS;
    }
}
