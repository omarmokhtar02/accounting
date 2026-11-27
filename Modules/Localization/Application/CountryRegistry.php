<?php

namespace Modules\Localization\Application;

use Modules\Localization\Domain\Contracts\CountryPack;
use Modules\Localization\Domain\Exceptions\UnknownCountry;

/**
 * The set of installed country packs. Each country module registers its pack from its own service
 * provider, so installing a country is adding a module and one provider line.
 */
final class CountryRegistry
{
    /** @var array<string, CountryPack> */
    private array $packs = [];

    public function register(CountryPack $pack): void
    {
        $code = $pack->profile()->code;

        if (isset($this->packs[$code])) {
            throw UnknownCountry::duplicate($code);
        }

        $this->packs[$code] = $pack;
    }

    public function has(string $code): bool
    {
        return isset($this->packs[strtoupper($code)]);
    }

    public function get(string $code): CountryPack
    {
        return $this->packs[strtoupper($code)] ?? throw UnknownCountry::forCode($code);
    }

    /** @return array<string, CountryPack> keyed by ISO code */
    public function all(): array
    {
        return $this->packs;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->packs);
    }
}
