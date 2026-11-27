<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Presentation\Controllers\TaxCodeController;

Route::middleware(['auth', 'verified', 'tenant', 'company', 'subscribed'])->get('/tax', [TaxCodeController::class, 'index'])
    ->middleware('can:tax.view')->name('tax.codes.index');
