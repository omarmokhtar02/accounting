<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\FiscalYear;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

it('hides another tenant\'s records even when the id is known', function () {
    ['company' => $companyA] = bootstrapTenant('a@example.com', 'Tenant A');
    $accountA = makeAccount($companyA, '1000');

    ['company' => $companyB] = bootstrapTenant('b@example.com', 'Tenant B');

    expect(Account::find($accountA->id))->toBeNull()
        ->and(Company::find($companyA->id))->toBeNull()
        ->and(Company::find($companyB->id))->not->toBeNull();
});

it('returns nothing and refuses writes without a tenant context', function () {
    bootstrapTenant();
    app(TenantContext::class)->clear();

    expect(Company::count())->toBe(0);

    Account::create(['company_id' => 1, 'code' => '1', 'name' => 'x', 'type' => 'asset']);
})->throws(InvalidTenantContext::class);

it('returns 404 for another tenant\'s journal entry over HTTP', function () {
    ['user' => $userA, 'company' => $companyA] = bootstrapTenant('a@example.com', 'Tenant A');
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    $entry = app(CreateJournalEntry::class)->handle(
        new CreateJournalEntryData(
            CarbonImmutable::parse('2026-02-01'), 'A entry',
            [
                new JournalLineData(makeAccount($companyA, '9001')->id, 100, 0),
                new JournalLineData(makeAccount($companyA, '9002')->id, 0, 100),
            ],
        ),
    );

    $userB = User::where('email', '!=', 'a@example.com')->first();
    ['user' => $userB] = bootstrapTenant('b@example.com', 'Tenant B');

    $this->actingAs($userB)
        ->get(route('accounting.journal-entries.show', $entry->id))
        ->assertNotFound();
});

it('prevents a user from a different company viewing the fiscal years of another company', function () {
    ['user' => $user, 'company' => $company] = bootstrapTenant();
    $second = Company::create(['name' => 'Second', 'country_code' => 'EG', 'base_currency' => 'EGP']);

    app(TenantContext::class)->setCompany($second);
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

    // The user is a member of the first company only, so the active company stays the first one.
    $this->actingAs($user)
        ->get(route('accounting.fiscal-years.index'))
        ->assertOk()
        ->assertDontSee('2026');

    // The tenant context is cleared after each request, so restore it before querying directly.
    app(TenantContext::class)->setTenant($user->tenant);

    expect(FiscalYear::where('company_id', $second->id)->count())->toBe(1);
});
