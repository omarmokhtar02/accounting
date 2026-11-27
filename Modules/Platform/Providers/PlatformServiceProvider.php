<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Presentation\Console\CreateAdminCommand;

class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Presentation/Views', 'platform');
        Blade::anonymousComponentPath(__DIR__.'/../Presentation/Views/components', 'platform');

        // @adminCan('tenants.manage') ... @endadminCan — permission checks for the admin guard in Blade.
        Blade::if('adminCan', fn (string $permission) => (bool) auth('admin')->user()?->hasPermission($permission));

        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class]);
        }
    }
}
