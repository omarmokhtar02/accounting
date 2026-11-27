<?php

namespace Modules\SaudiArabia\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Localization\Application\CountryRegistry;
use Modules\SaudiArabia\Domain\SaudiCountryPack;

class SaudiArabiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(CountryRegistry::class, fn (CountryRegistry $registry) => $registry->register(new SaudiCountryPack));
    }
}
