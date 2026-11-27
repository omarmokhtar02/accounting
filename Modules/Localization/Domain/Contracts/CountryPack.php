<?php

namespace Modules\Localization\Domain\Contracts;

use Modules\Accounting\Domain\ValueObjects\AccountSeed;
use Modules\Localization\Domain\CountryProfile;
use Modules\Tax\Domain\ValueObjects\TaxCodeSeed;

/**
 * Everything that differs per country lives behind this contract, one implementation per country
 * module. Core code never branches on a country code: it asks the company's pack.
 * Later milestones add: tax pack, statutory statement layouts, e-invoice provider, statutory reports.
 */
interface CountryPack
{
    public function profile(): CountryProfile;

    /** @return list<AccountSeed> */
    public function chartOfAccounts(): array;

    /**
     * The tax codes this country uses, each with optional DEFAULT rates by effective date. Rates are data:
     * the platform admin can change them at any time without a release.
     *
     * @return list<TaxCodeSeed>
     */
    public function taxCodes(): array;

    /** Bump when the pack's data changes so companies record which version they were created from. */
    public function version(): string;
}
