<?php

use Modules\Localization\Presentation\Controllers\CompanyController;
use Modules\Tenancy\Domain\Models\Company;

covers(CompanyController::class);

beforeEach(function () {
    ['user' => $this->owner] = bootstrapTenant();
});

it('lists the user\'s companies with country, currency and pack version', function () {
    $this->get(route('localization.companies.index'))->assertOk()->assertSee('Acme')->assertSee('Egypt')->assertSee('EGP')->assertSee('2026.1');
});

it('shows the Arabic country name in Arabic', function () {
    $this->withSession(['locale' => 'ar'])->get(route('localization.companies.index'))->assertOk()->assertSee('مصر');
});

it('offers every installed country with its currency and standard', function () {
    $this->get(route('localization.companies.create'))->assertOk()
        ->assertSee('Egypt')->assertSee('Saudi Arabia')->assertSee('Kuwait')->assertSee('KWD')->assertSee('SOCPA');
});

it('creates a company for a country with its chart and gives the creator access', function (string $code, string $currency) {
    $this->post(route('localization.companies.store'), ['name' => "New {$code}", 'name_ar' => 'جديدة', 'country_code' => $code])
        ->assertRedirect(route('localization.companies.index'));

    restoreContext($this->owner);
    $company = Company::withoutGlobalScopes()->where('name', "New {$code}")->first();

    expect($company->base_currency)->toBe($currency)->and($company->accounts()->count())->toBeGreaterThan(50)
        ->and($company->users->pluck('id'))->toContain($this->owner->id);
})->with([['SA', 'SAR'], ['KW', 'KWD'], ['EG', 'EGP']]);

it('validates the new company', function (array $input, string $field) {
    $this->post(route('localization.companies.store'), $input + ['name' => 'X', 'country_code' => 'EG'])->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'unknown country' => [['country_code' => 'ZZ'], 'country_code'],
    'no country' => [['country_code' => ''], 'country_code'],
]);

it('requires companies.manage to create', function () {
    actingAsRole('senior_accountant');

    $this->get(route('localization.companies.index'))->assertOk();
    $this->get(route('localization.companies.create'))->assertForbidden();
    $this->post(route('localization.companies.store'), ['name' => 'X', 'country_code' => 'EG'])->assertForbidden();
});
