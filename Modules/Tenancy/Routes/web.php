<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Presentation\Controllers\ActiveCompanyController;

Route::middleware(['auth', 'verified', 'tenant'])->post('/company/switch', [ActiveCompanyController::class, 'update'])->name('company.switch');
