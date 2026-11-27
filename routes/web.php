<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Module routes (Accounting, Identity) are registered by their service providers.

Route::get('/health', HealthController::class)->name('health');

// No marketing page: the product opens on the dashboard, or on the login form when signed out.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified', 'tenant', 'subscribed'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
