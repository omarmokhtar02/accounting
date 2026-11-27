<?php

namespace Modules\Localization\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Localization\Application\CountryRegistry;
use Modules\Localization\Presentation\Console\SyncTaxDefaultsCommand;

class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CountryRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Presentation/Views', 'localization');

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncTaxDefaultsCommand::class]);
        }
    }
}
