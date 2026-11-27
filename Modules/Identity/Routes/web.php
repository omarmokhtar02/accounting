<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Controllers\LocaleController;
use Modules\Identity\Presentation\Controllers\RoleController;
use Modules\Identity\Presentation\Controllers\UserController;

Route::get('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified', 'tenant', 'company', 'subscribed'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->middleware('can:users.manage')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->middleware('can:users.manage')->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('can:users.manage')->name('users.store');

    Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.manage')->name('roles.index');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('can:roles.manage')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.manage')->name('roles.update');
});
