<?php

use Illuminate\Support\Facades\Route;
use Modules\Localization\Presentation\Controllers\CompanyController;

Route::middleware(['auth', 'verified', 'tenant', 'company', 'subscribed'])->prefix('companies')->name('localization.companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('create', [CompanyController::class, 'create'])->middleware('can:companies.manage')->name('create');
    Route::post('/', [CompanyController::class, 'store'])->middleware('can:companies.manage')->name('store');
});
