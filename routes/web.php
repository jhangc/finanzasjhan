<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\CommitmentController;
use App\Http\Controllers\CommitmentPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvelopeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\IncomeSourceController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\RecurringRuleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Services\Charts\ChartData;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/anotar', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/anotar', [TransactionController::class, 'store'])->name('transactions.store');

    Route::post('/income-sources', [IncomeSourceController::class, 'store'])->name('income-sources.store');
    Route::delete('/income-sources', [IncomeSourceController::class, 'destroy'])->name('income-sources.destroy');

    Route::post('/movements/quick', [MovementController::class, 'quick'])
        ->middleware('throttle:quick-entry')->name('movements.quick');

    Route::get('/periods/setup', [PeriodController::class, 'setup'])->name('periods.setup');
    Route::put('/periods/setup', [PeriodController::class, 'save'])->name('periods.save');
    Route::post('/periods/setup/copy-previous', [PeriodController::class, 'copyPrevious'])->name('periods.copy-previous');

    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('/charts', [ChartController::class, 'index'])->name('charts.index');
    Route::get('/charts/data/{type}', [ChartController::class, 'data'])
        ->whereIn('type', ChartData::TYPES)->name('charts.data');

    Route::get('/export', ExportController::class)->name('export');
    Route::post('/import', ImportController::class)->middleware('throttle:6,1')->name('import');

    Route::get('/vouchers/{voucher}', [CommitmentPaymentController::class, 'voucher'])->name('vouchers.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/me', [UserController::class, 'updateMe'])->name('users.me');

    // Los sobres se editan en "Armar el mes" y el resto en sus pantallas, sin formularios aparte.
    Route::post('/envelopes/batch', [EnvelopeController::class, 'batch'])->name('envelopes.batch');
    Route::resource('envelopes', EnvelopeController::class)->only(['store', 'update', 'destroy']);
    Route::resource('movements', MovementController::class)->only(['store', 'update', 'destroy']);
    Route::resource('incomes', IncomeController::class)->only(['store', 'update', 'destroy']);
    Route::resource('commitments', CommitmentController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('commitment-payments', CommitmentPaymentController::class)->only(['store', 'update', 'destroy']);
    Route::resource('recurring-rules', RecurringRuleController::class)->only(['store', 'update', 'destroy']);
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);
});
