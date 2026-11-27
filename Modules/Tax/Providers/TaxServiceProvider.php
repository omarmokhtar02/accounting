<?php

namespace Modules\Tax\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Tax\Application\TaxRateResolver;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One resolver per request so a rate the admin just changed is seen by every consumer at once.
        $this->app->scoped(TaxRateResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Presentation/Views', 'tax');

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
    }
}
