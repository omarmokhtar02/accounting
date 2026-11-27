<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Exceptions\ChartAlreadyExists;
use Modules\Accounting\Domain\Models\Account;
use Modules\Localization\Application\ApplyCountryPack;
use Modules\Localization\Application\CountryRegistry;
use Modules\Localization\Application\CreateCompany;
use Modules\Localization\Domain\ChartBuilder;
use Modules\Localization\Domain\Contracts\CountryPack;
use Modules\Localization\Domain\CountryProfile;
use Modules\Localization\Domain\Exceptions\UnknownCountry;
use Modules\Localization\Providers\LocalizationServiceProvider;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(ApplyCountryPack::class, CountryRegistry::class, CreateCompany::class, ChartBuilder::class, CountryProfile::class,
    UnknownCountry::class, LocalizationServiceProvider::class);

function profile(array $override = []): CountryProfile
{
    return new CountryProfile(...array_merge([
        'code' => 'EG', 'nameEn' => 'Egypt', 'nameAr' => 'مصر', 'currency' => 'EGP', 'locale' => 'ar',
        'fiscalYearStartMonth' => 1, 'accountingStandard' => 'EAS', 'vatEnabled' => true, 'withholdingTax' => true,
    ], $override));
}

it('validates a country profile', function (array $bad) {
    profile($bad);
})->with([[['code' => 'egy']], [['code' => 'E']], [['fiscalYearStartMonth' => 0]], [['fiscalYearStartMonth' => 13]]])
    ->throws(InvalidArgumentException::class);

it('lists the roles a country needs mapped', function () {
    $roles = fn (CountryProfile $p) => array_map(fn ($r) => $r->value, $p->requiredRoles());

    expect($roles(profile()))->toContain('vat_input', 'vat_output', 'withholding_tax_payable', 'withholding_tax_receivable', 'cash', 'accounts_receivable')
        ->and($roles(profile(['vatEnabled' => false, 'withholdingTax' => false])))->not->toContain('vat_input')->not->toContain('withholding_tax_payable')
        ->and($roles(profile(['vatEnabled' => true, 'withholdingTax' => false])))->toContain('vat_input')->not->toContain('withholding_tax_payable');
});

it('defaults the weekend and Hijri display', function () {
    expect(profile()->weekend)->toBe(['friday', 'saturday'])->and(profile()->hijriDisplay)->toBeFalse();
});

it('builds a chart with headers and leaves', function () {
    $seeds = (new ChartBuilder)
        ->header('1', 'Assets', 'الأصول', AccountType::Asset)
        ->leaf('1101', 'Cash', 'النقدية', AccountType::Asset, '1', AccountRole::Cash, CashFlowCategory::Cash)
        ->build();

    expect($seeds)->toHaveCount(2)
        ->and($seeds[0]->postable)->toBeFalse()->and($seeds[0]->parentCode)->toBeNull()
        ->and($seeds[1]->postable)->toBeTrue()->and($seeds[1]->parentCode)->toBe('1')
        ->and($seeds[1]->role)->toBe(AccountRole::Cash)->and($seeds[1]->cashFlow)->toBe(CashFlowCategory::Cash);
});

it('registers, finds and lists packs', function () {
    $registry = new CountryRegistry;
    $pack = new class implements CountryPack
    {
        public function profile(): CountryProfile
        {
            return profile(['code' => 'ZZ']);
        }

        public function chartOfAccounts(): array
        {
            return [];
        }

        public function taxCodes(): array
        {
            return [];
        }

        public function version(): string
        {
            return '1';
        }
    };

    $registry->register($pack);

    expect($registry->has('zz'))->toBeTrue()->and($registry->get('ZZ'))->toBe($pack)
        ->and($registry->codes())->toBe(['ZZ'])->and($registry->all())->toHaveKey('ZZ')->and($registry->has('QQ'))->toBeFalse();
});

it('refuses duplicate and unknown countries', function () {
    $registry = app(CountryRegistry::class);

    expect(fn () => $registry->get('ZZ'))->toThrow(UnknownCountry::class)
        ->and(fn () => $registry->register($registry->get('EG')))->toThrow(UnknownCountry::class)
        ->and(UnknownCountry::forCode('ZZ')->getMessage())->toContain('ZZ')
        ->and(UnknownCountry::duplicate('EG')->getMessage())->toContain('EG');
});

it('shares one registry singleton holding the launch countries', function () {
    expect(app(CountryRegistry::class))->toBe(app(CountryRegistry::class))
        ->and(app(CountryRegistry::class)->codes())->toContain('EG', 'SA', 'KW');
});

it('creates a company for each country with the right currency and decimals', function (string $code, string $currency) {
    bootstrapTenant("cc-{$code}@example.com", 'First', 'EG');

    $company = app(CreateCompany::class)->handle("Second {$code}", 'ثاني', $code);

    expect($company->country_code)->toBe($code)->and($company->base_currency)->toBe($currency)
        ->and($company->name_ar)->toBe('ثاني')->and($company->country_pack_version)->toBe('2026.1')
        ->and(Account::where('company_id', $company->id)->count())->toBeGreaterThan(50)
        ->and(app(TenantContext::class)->companyId())->toBe($company->id);
})->with([['EG', 'EGP'], ['SA', 'SAR'], ['KW', 'KWD']]);

it('lets one tenant hold companies of different countries side by side', function () {
    bootstrapTenant('multi@example.com', 'Group', 'EG');
    $creator = app(CreateCompany::class);
    $sa = $creator->handle('Riyadh', null, 'SA');
    $kw = $creator->handle('Kuwait City', null, 'KW');

    expect(Company::pluck('country_code')->sort()->values()->all())->toBe(['EG', 'KW', 'SA'])
        ->and(Account::where('company_id', $sa->id)->where('code', '2107')->first()->name)->toBe('Zakat payable')
        ->and(Account::where('company_id', $kw->id)->where('code', '2105')->first()->name)->toContain('PIFSS');
});

it('rejects an unknown country without creating a company', function () {
    bootstrapTenant();
    $before = Company::count();

    expect(fn () => app(CreateCompany::class)->handle('X', null, 'ZZ'))->toThrow(UnknownCountry::class)
        ->and(Company::count())->toBe($before);
});

it('audits company creation and pack application', function () {
    bootstrapTenant();
    $company = app(CreateCompany::class)->handle('Audited', null, 'KW');

    expect(AuditLog::where('entity_type', 'company')->where('entity_id', $company->id)->pluck('action')->sort()->values()->all())
        ->toBe(['chart_seeded', 'country_pack_applied', 'created']);
});

it('refuses to apply a pack twice to the same company', function () {
    ['company' => $company] = bootstrapTenant();

    app(ApplyCountryPack::class)->handle($company);
})->throws(ChartAlreadyExists::class);
