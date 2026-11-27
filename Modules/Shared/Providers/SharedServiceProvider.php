<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\PlatformSettings;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;
use Modules\Shared\Infrastructure\DatabaseCurrencyRegistry;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrencyRegistry::class, DatabaseCurrencyRegistry::class);

        // One instance per request, so a setting an admin just changed is seen by every consumer at once.
        $this->app->scoped(PlatformSettings::class);
    }
}
