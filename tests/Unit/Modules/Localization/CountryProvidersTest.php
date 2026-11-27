<?php

use Modules\Egypt\Domain\EgyptCountryPack;
use Modules\Egypt\Providers\EgyptServiceProvider;
use Modules\Kuwait\Domain\KuwaitCountryPack;
use Modules\Kuwait\Providers\KuwaitServiceProvider;
use Modules\Localization\Application\CountryRegistry;
use Modules\SaudiArabia\Domain\SaudiCountryPack;
use Modules\SaudiArabia\Providers\SaudiArabiaServiceProvider;

covers(EgyptServiceProvider::class, SaudiArabiaServiceProvider::class, KuwaitServiceProvider::class);

it('registers each country pack with the registry from its own provider', function (string $code, string $packClass) {
    expect(app(CountryRegistry::class)->get($code))->toBeInstanceOf($packClass);
})->with([['EG', EgyptCountryPack::class], ['SA', SaudiCountryPack::class], ['KW', KuwaitCountryPack::class]]);

it('registers a pack only once even when the registry is resolved repeatedly', function () {
    app(CountryRegistry::class);
    app(CountryRegistry::class);

    expect(app(CountryRegistry::class)->codes())->toBe(['EG', 'SA', 'KW']);
});
