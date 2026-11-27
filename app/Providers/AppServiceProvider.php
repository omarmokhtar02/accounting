<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared database: central (landlord) and tenant-owned tables migrate on one connection.
        $this->loadMigrationsFrom([
            database_path('migrations/central'),
            database_path('migrations/tenant'),
        ]);

        $production = $this->app->isProduction();

        // Catch typos and silently dropped attributes during development and tests.
        Model::preventAccessingMissingAttributes(! $production);
        Model::preventSilentlyDiscardingAttributes(! $production);

        // Never allow migrate:fresh / db:wipe against production data.
        DB::prohibitDestructiveCommands($production);

        if ($production) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $production
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));
    }
}
