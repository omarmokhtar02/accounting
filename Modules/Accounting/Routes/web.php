<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Presentation\Controllers\AccountController;
use Modules\Accounting\Presentation\Controllers\AccountRoleController;
use Modules\Accounting\Presentation\Controllers\ExchangeRateController;
use Modules\Accounting\Presentation\Controllers\FiscalYearController;
use Modules\Accounting\Presentation\Controllers\ImportController;
use Modules\Accounting\Presentation\Controllers\JournalEntryController;
use Modules\Accounting\Presentation\Controllers\PeriodController;
use Modules\Accounting\Presentation\Controllers\ReportController;

Route::middleware(['auth', 'verified', 'tenant', 'company', 'subscribed'])->prefix('accounting')->name('accounting.')->group(function () {
    Route::get('accounts', [AccountController::class, 'index'])->middleware('can:accounts.view')->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->middleware('can:accounts.create')->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('can:accounts.create')->name('accounts.store');

    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->middleware('can:accounts.update')->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->middleware('can:accounts.update')->name('accounts.update');
    Route::post('accounts/{account}/status', [AccountController::class, 'changeStatus'])->middleware('can:accounts.archive')->name('accounts.status');

    Route::get('account-roles', [AccountRoleController::class, 'index'])->middleware('can:accounts.view')->name('account-roles.index');
    Route::put('account-roles', [AccountRoleController::class, 'update'])->middleware('can:accounts.update')->name('account-roles.update');

    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance'])->middleware('can:reports.view')->name('reports.trial-balance');
    Route::get('reports/general-ledger', [ReportController::class, 'generalLedger'])->middleware('can:reports.view')->name('reports.general-ledger');

    Route::get('fiscal-years', [FiscalYearController::class, 'index'])->middleware('can:periods.view')->name('fiscal-years.index');
    Route::get('fiscal-years/create', [FiscalYearController::class, 'create'])->middleware('can:periods.manage')->name('fiscal-years.create');
    Route::post('fiscal-years', [FiscalYearController::class, 'store'])->middleware('can:periods.manage')->name('fiscal-years.store');
    Route::post('periods/{period}/close', [PeriodController::class, 'close'])->middleware('can:periods.close')->name('periods.close');

    Route::get('journal-entries', [JournalEntryController::class, 'index'])->middleware('can:journals.view')->name('journal-entries.index');
    Route::get('journal-entries/create', [JournalEntryController::class, 'create'])->middleware('can:journals.create')->name('journal-entries.create');
    Route::post('journal-entries', [JournalEntryController::class, 'store'])->middleware('can:journals.create')->name('journal-entries.store');
    Route::get('journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->middleware('can:journals.view')->name('journal-entries.show');
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->middleware('can:journals.post')->name('journal-entries.post');
    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->middleware('can:journals.reverse')->name('journal-entries.reverse');
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->middleware('can:accounts.view')->name('exchange-rates.index');
    Route::post('exchange-rates', [ExchangeRateController::class, 'store'])->middleware('can:accounts.update')->name('exchange-rates.store');

    Route::get('imports', [ImportController::class, 'index'])->middleware('can:imports.run')->name('imports.index');
    Route::get('imports/template/{type}/{format}', [ImportController::class, 'template'])->middleware('can:imports.run')->name('imports.template');
    Route::post('imports/preview', [ImportController::class, 'preview'])->middleware('can:imports.run')->name('imports.preview');
    Route::post('imports/commit', [ImportController::class, 'commit'])->middleware('can:imports.run')->name('imports.commit');
});
