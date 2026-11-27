<?php

namespace Modules\Kuwait\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Kuwait\Domain\KuwaitCountryPack;
use Modules\Localization\Application\CountryRegistry;

class KuwaitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(CountryRegistry::class, fn (CountryRegistry $registry) => $registry->register(new KuwaitCountryPack));
    }
}
