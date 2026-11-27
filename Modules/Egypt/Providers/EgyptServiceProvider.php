<?php

namespace Modules\Egypt\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Egypt\Domain\EgyptCountryPack;
use Modules\Localization\Application\CountryRegistry;

class EgyptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(CountryRegistry::class, fn (CountryRegistry $registry) => $registry->register(new EgyptCountryPack));
    }
}
