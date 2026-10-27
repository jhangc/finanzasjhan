<?php

use Illuminate\Support\Facades\Schedule;

/*
| Cron del servidor: * * * * * php artisan schedule:run
| Horarios en America/Lima (zona de la app).
*/

Schedule::command('fincontrol:generate-period')->monthlyOn(1, '00:05')->withoutOverlapping();
Schedule::command('fincontrol:payment-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('fincontrol:monthly-summary')->monthlyOn(1, '08:00')->withoutOverlapping();

Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
