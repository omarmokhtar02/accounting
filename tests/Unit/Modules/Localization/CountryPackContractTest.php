<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\AccountMapping;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Accounting\Domain\Services\ChartOfAccountsValidator;
use Modules\Localization\Application\CountryRegistry;
use Modules\Shared\Domain\Contracts\CurrencyRegistry;

/*
 * The contract EVERY country pack must satisfy. A new country module is added to this dataset
 * automatically (it comes from the registry), and CI fails until the pack passes.
 */
uses(RefreshDatabase::class);

// Datasets resolve before the app boots, so discover country modules from the filesystem:
// any Modules/<Country>/Domain/*CountryPack.php is picked up automatically.
dataset('country packs', function () {
    $codes = [];

    foreach (glob(dirname(__DIR__, 4).'/Modules/*/Domain/*CountryPack.php') as $file) {
        $class = 'Modules\\'.basename(dirname($file, 2)).'\\Domain\\'.basename($file, '.php');
        $codes[] = [(new $class)->profile()->code];
    }

    return $codes;
});

it('has the three launch countries installed', function () {
    expect(app(CountryRegistry::class)->codes())->toContain('EG', 'SA', 'KW');
});

it('has a valid profile', function (string $code) {
    $pack = app(CountryRegistry::class)->get($code);
    $profile = $pack->profile();

    expect($profile->code)->toBe($code)
        ->and($profile->nameEn)->not->toBeEmpty()
        ->and($profile->nameAr)->not->toBeEmpty()
        ->and($profile->accountingStandard)->not->toBeEmpty()
        ->and($pack->version())->toMatch('/^\d{4}\.\d+$/')
        ->and(app(CurrencyRegistry::class)->exists($profile->currency))->toBeTrue();
})->with('country packs');

it('ships a structurally valid chart of accounts with every required role', function (string $code) {
    $pack = app(CountryRegistry::class)->get($code);

    expect((new ChartOfAccountsValidator)->problems($pack->chartOfAccounts(), $pack->profile()->requiredRoles()))->toBe([]);
})->with('country packs');

it('names every account in English and Arabic', function (string $code) {
    foreach (app(CountryRegistry::class)->get($code)->chartOfAccounts() as $seed) {
        expect(trim($seed->nameEn))->not->toBeEmpty()
            ->and(preg_match('/\p{Arabic}/u', $seed->nameAr))->toBe(1, "Account {$seed->code} Arabic name has no Arabic letters");
    }
})->with('country packs');

it('puts cash and bank accounts in the cash category of the cash-flow statement', function (string $code) {
    $seeds = collect(app(CountryRegistry::class)->get($code)->chartOfAccounts());

    foreach ([AccountRole::Cash, AccountRole::Bank] as $role) {
        expect($seeds->firstWhere('role', $role)->cashFlow)->toBe(CashFlowCategory::Cash);
    }
})->with('country packs');

it('only requires VAT and withholding roles where the country levies them', function (string $code) {
    $pack = app(CountryRegistry::class)->get($code);
    $profile = $pack->profile();
    $roles = collect($pack->chartOfAccounts())->pluck('role')->filter()->all();

    expect(in_array(AccountRole::VatInput, $profile->requiredRoles(), true))->toBe($profile->vatEnabled)
        ->and(in_array(AccountRole::WithholdingTaxPayable, $profile->requiredRoles(), true))->toBe($profile->withholdingTax);

    // A country without VAT must not carry VAT accounts at all.
    if (! $profile->vatEnabled) {
        expect($roles)->not->toContain(AccountRole::VatInput)->not->toContain(AccountRole::VatOutput);
    }
})->with('country packs');

it('creates a company with the pack chart, base currency and role mappings', function (string $code) {
    ['company' => $company] = bootstrapTenant("pack-{$code}@example.com", "Co {$code}", $code);
    $pack = app(CountryRegistry::class)->get($code);

    expect($company->country_code)->toBe($code)
        ->and($company->base_currency)->toBe($pack->profile()->currency)
        ->and($company->country_pack_version)->toBe($pack->version())
        ->and(Account::count())->toBe(count($pack->chartOfAccounts()))
        ->and(AccountMapping::count())->toBe(collect($pack->chartOfAccounts())->whereNotNull('role')->count());

    $resolver = app(AccountRoleResolver::class);
    foreach ($pack->profile()->requiredRoles() as $role) {
        expect(Account::find($resolver->accountId($role))->type)->toBe($role->expectedType());
    }
})->with('country packs');

it('keeps the ledger balanced end to end after the pack is applied', function (string $code) {
    ['user' => $user] = bootstrapTenant("tb-{$code}@example.com", "TB {$code}", $code);
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    $resolver = app(AccountRoleResolver::class);

    $entry = app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse('2026-03-10'), 'Sample cash sale',
        [new JournalLineData($resolver->accountId(AccountRole::Cash), 123456, 0), new JournalLineData($resolver->accountId(AccountRole::SalesRevenue), 0, 123456)],
    ));
    app(PostJournalEntry::class)->handle($entry, $user, allowSelfPosting: true);

    expect((int) JournalLine::sum('debit'))->toBe((int) JournalLine::sum('credit'))->toBe(123456);
})->with('country packs');
