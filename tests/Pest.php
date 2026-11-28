<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Identity\Application\CreateTenantUser;
use Modules\Identity\Application\RegisterTenant;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
pest()->use(RefreshDatabase::class)->in('Unit/Modules', 'Unit/App');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Creates a tenant with an owner, one company and an open fiscal year, and initialises the tenant context.
 *
 * @return array{user: User, company: Company}
 */
function bootstrapTenant(string $email = 'owner@example.com', string $name = 'Acme', string $country = 'EG'): array
{
    // A prior actingAsAdmin() leaves 'admin' as the default guard, which would audit the admin's id as a user id.
    Auth::shouldUse('web');

    $user = app(RegisterTenant::class)->handle(
        'Owner', $email, Hash::make('password'), $name, $country, 'en',
    );

    $company = $user->companies()->first();
    app(TenantContext::class)->setTenant($user->tenant);
    app(TenantContext::class)->setCompany($company);
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
    test()->actingAs($user);

    return ['user' => $user, 'company' => $company];
}

function makeAccount(Company $company, string $code, AccountType $type = AccountType::Asset): Account
{
    return Account::create([
        'company_id' => $company->id, 'code' => $code, 'name' => 'Account '.$code, 'type' => $type,
    ])->refresh();
}

function openFiscalYear2026(): void
{
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
}

function accountByRole(AccountRole $role): Account
{
    return app(AccountRoleResolver::class)->account($role);
}

/** Creates a balanced draft journal Dr cash / Cr sales (minor units) in the active company. */
function draftJournal(int $amount = 10000, string $date = '2026-03-15', ?string $key = null): JournalEntry
{
    return app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse($date),
        'Test sale',
        [
            new JournalLineData(accountByRole(AccountRole::Cash)->id, $amount, 0),
            new JournalLineData(accountByRole(AccountRole::SalesRevenue)->id, 0, $amount),
        ],
        $key,
    ));
}

function postedJournal(User $poster, int $amount = 10000, string $date = '2026-03-15'): JournalEntry
{
    return app(PostJournalEntry::class)->handle(draftJournal($amount, $date), $poster, allowSelfPosting: true);
}

/** A second user in the active tenant/company (default role: senior accountant, may post). */
function bootstrapTenantUser(string $role = 'senior_accountant'): User
{
    $context = app(TenantContext::class);

    return app(CreateTenantUser::class)->handle(
        'Colleague', uniqid('colleague').'@example.com', Hash::make('password'), $role, [$context->companyId()],
    );
}

/** Logs in as a new colleague of the active tenant with the given role. */
function actingAsRole(string $role): User
{
    $user = bootstrapTenantUser($role);
    test()->actingAs($user);

    return $user;
}

/** Controllers clear the tenant context after each request; restore it before asserting on tenant data. */
function restoreContext(User $user): void
{
    $context = app(TenantContext::class);
    $context->setTenant($user->tenant);
    $context->setCompany($user->companies()->first());
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
}

/** Creates a platform admin with the given built-in role key. */
function makeAdmin(string $roleKey = 'super_admin', array $attributes = []): AdminUser
{
    return AdminUser::create(array_merge([
        'name' => 'Admin '.uniqid(),
        'email' => uniqid('admin').'@example.com',
        'password' => 'correct-horse-battery',
        'admin_role_id' => AdminRole::where('key', $roleKey)->value('id'),
        'is_active' => true,
    ], $attributes))->refresh();
}

/** Logs in as a platform admin on the "admin" guard. */
function actingAsAdmin(string $roleKey = 'super_admin'): AdminUser
{
    $admin = makeAdmin($roleKey);
    test()->actingAs($admin, 'admin');

    return $admin;
}
