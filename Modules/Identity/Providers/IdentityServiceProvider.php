<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Presentation/Views', 'identity');

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
    }
}
