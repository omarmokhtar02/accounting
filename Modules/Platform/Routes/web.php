<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Presentation\Controllers\AdminRoleController;
use Modules\Platform\Presentation\Controllers\AdminUserController;
use Modules\Platform\Presentation\Controllers\AuditController;
use Modules\Platform\Presentation\Controllers\AuthController;
use Modules\Platform\Presentation\Controllers\DashboardController;
use Modules\Platform\Presentation\Controllers\PlanController;
use Modules\Platform\Presentation\Controllers\PlatformLocaleController;
use Modules\Platform\Presentation\Controllers\SettingsController;
use Modules\Platform\Presentation\Controllers\TaxRateController;
use Modules\Platform\Presentation\Controllers\TenantController;

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('locale/{locale}', [PlatformLocaleController::class, 'update'])->name('locale');

    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:admin', 'platform.active'])->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->middleware('platform.can:dashboard.view')->name('dashboard');

        Route::get('tenants', [TenantController::class, 'index'])->middleware('platform.can:tenants.view')->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('platform.can:tenants.view')->name('tenants.show');
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->middleware('platform.can:tenants.manage')->name('tenants.suspend');
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->middleware('platform.can:tenants.manage')->name('tenants.activate');

        Route::middleware('platform.can:subscriptions.manage')->prefix('tenants/{tenant}/subscription')->name('subscriptions.')->group(function () {
            Route::post('extend-trial', [TenantController::class, 'extendTrial'])->name('extend-trial');
            Route::post('change-plan', [TenantController::class, 'changePlan'])->name('change-plan');
            Route::post('activate', [TenantController::class, 'activateSubscription'])->name('activate');
            Route::post('grant-plan', [TenantController::class, 'grantPlan'])->name('grant-plan');
            Route::post('grant-all', [TenantController::class, 'grantAllPlans'])->name('grant-all');
            Route::post('cancel', [TenantController::class, 'cancel'])->name('cancel');
            Route::post('payments', [TenantController::class, 'recordPayment'])->name('payments');
        });

        Route::middleware('platform.can:plans.manage')->group(function () {
            Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
            Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
            Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
            Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
            Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        });

        Route::middleware('platform.can:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('platform.can:admins.manage')->group(function () {
            Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
            Route::get('admins/create', [AdminUserController::class, 'create'])->name('admins.create');
            Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
            Route::get('admins/{admin}/edit', [AdminUserController::class, 'edit'])->name('admins.edit');
            Route::put('admins/{admin}', [AdminUserController::class, 'update'])->name('admins.update');
        });

        Route::middleware('platform.can:roles.manage')->group(function () {
            Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [AdminRoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [AdminRoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [AdminRoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::middleware('platform.can:tax.manage')->group(function () {
            Route::get('tax-rates', [TaxRateController::class, 'index'])->name('tax-rates.index');
            Route::post('tax-rates', [TaxRateController::class, 'store'])->name('tax-rates.store');
            Route::delete('tax-rates/{schedule}', [TaxRateController::class, 'destroy'])->name('tax-rates.destroy');
        });

        Route::get('audit', AuditController::class)->middleware('platform.can:audit.view')->name('audit.index');
    });
});
