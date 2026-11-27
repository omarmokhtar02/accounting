<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Domain\Models\Tenant;

covers(Company::class, Tenant::class, AuditLog::class);

it('creates tenants and companies from factories', function () {
    $tenant = Tenant::factory()->create();
    $company = Company::factory()->create(['tenant_id' => $tenant->id]);

    expect($tenant->status)->toBe('active')->and($company->tenant_id)->toBe($tenant->id)
        ->and($company->country_code)->toBe('EG')->and($company->base_currency)->toBe('EGP');
});

it('has factory states for the other launch countries', function () {
    $saudi = Company::factory()->saudi()->create();
    $kuwaiti = Company::factory()->kuwaiti()->create();

    expect([$saudi->country_code, $saudi->base_currency])->toBe(['SA', 'SAR'])
        ->and([$kuwaiti->country_code, $kuwaiti->base_currency])->toBe(['KW', 'KWD'])
        ->and(Tenant::factory()->suspended()->make()->status)->toBe('suspended');
});

it('links a tenant to its companies and users, and a company to its members', function () {
    ['user' => $user, 'company' => $company] = bootstrapTenant();

    expect($user->tenant->companies->pluck('id'))->toContain($company->id)
        ->and($user->tenant->users->pluck('id'))->toContain($user->id)
        ->and($company->users->pluck('id'))->toContain($user->id)
        ->and($company->accounts()->count())->toBeGreaterThan(50)
        ->and($company->fiscalYears()->count())->toBe(0);
});

it('shows the Arabic company name in Arabic', function () {
    ['company' => $company] = bootstrapTenant();
    $company->update(['name' => 'Acme', 'name_ar' => 'أكمي']);

    app()->setLocale('ar');
    expect($company->localizedName())->toBe('أكمي');

    app()->setLocale('en');
    expect($company->localizedName())->toBe('Acme');
});

it('protects tenant_id from mass assignment on users', function () {
    expect(fn () => new User(['name' => 'x', 'email' => 'x@example.com', 'password' => 'p', 'tenant_id' => 99]))
        ->toThrow(MassAssignmentException::class);
});

it('casts audit values to arrays and has no timestamps', function () {
    ['company' => $company] = bootstrapTenant();
    $log = AuditLog::create([
        'company_id' => $company->id, 'entity_type' => 'x', 'entity_id' => 1, 'action' => 'a',
        'old_values' => ['a' => 1], 'new_values' => ['b' => 2], 'occurred_at' => now(),
    ]);

    expect($log->fresh()->old_values)->toBe(['a' => 1])->and($log->new_values)->toBe(['b' => 2])
        ->and($log->timestamps)->toBeFalse()->and($log->occurred_at)->not->toBeNull();
});
