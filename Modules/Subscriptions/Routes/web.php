<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Presentation\Controllers\BillingController;

// The billing page is reachable even when the subscription is blocked (that is where the user is sent).
Route::middleware(['auth', 'verified', 'tenant'])->get('/billing', [BillingController::class, 'show'])->name('billing.show');
