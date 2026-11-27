<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Modules\Localization\Application\CreateCompany;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\TenantContext;
use Modules\Tenancy\Presentation\CompanySwitcherComposer;
use Modules\Tenancy\Presentation\Controllers\ActiveCompanyController;
use Modules\Tenancy\Presentation\Middleware\InitializeTenantContext;
use Modules\Tenancy\Presentation\Middleware\RequireCompany;
use Modules\Tenancy\Providers\TenancyServiceProvider;

covers(CompanySwitcherComposer::class, ActiveCompanyController::class, InitializeTenantContext::class, RequireCompany::class, TenancyServiceProvider::class);

describe('InitializeTenantContext', function () {
    it('resolves the tenant and the member company from the logged-in user, never from input', function () {
        ['user' => $user, 'company' => $company] = bootstrapTenant();

        $this->get(route('dashboard', ['tenant_id' => 999, 'company_id' => 999]))->assertOk();

        // The user's own company was active; the forged ids changed nothing.
        expect(session('company_id'))->toBe($company->id);
    });

    it('rejects guests, users without a tenant, and suspended tenants', function () {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        ['user' => $user] = bootstrapTenant();
        Tenant::where('id', $user->tenant_id)->update(['status' => 'suspended']);
        $this->actingAs($user->fresh());

        $this->get(route('dashboard'))->assertForbidden();
    });

    it('rejects a user whose tenant is missing', function () {
        $user = User::factory()->create()->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    });

    it('falls back to the first member company when the session company is not a membership', function () {
        ['user' => $user, 'company' => $company] = bootstrapTenant();

        $this->withSession(['company_id' => 99999])->get(route('dashboard'))->assertOk();

        expect(session('company_id'))->toBe($company->id);
    });

    it('clears the context after the response so a pooled connection carries nothing over', function () {
        bootstrapTenant();

        $this->get(route('dashboard'))->assertOk();

        expect(app(TenantContext::class)->tenantId())->toBeNull();
    });
});

describe('RequireCompany', function () {
    it('blocks users that belong to no company', function () {
        ['user' => $user] = bootstrapTenant();
        $user->companies()->detach();

        $this->get(route('accounting.accounts.index'))->assertForbidden();
    });
});

describe('active company switching', function () {
    beforeEach(function () {
        ['user' => $this->user, 'company' => $this->first] = bootstrapTenant();
        $this->second = app(CreateCompany::class)->handle('Riyadh Co', null, 'SA');
        $this->second->users()->attach($this->user->id);
        restoreContext($this->user);
    });

    it('switches to a company the user belongs to', function () {
        $this->post(route('company.switch'), ['company_id' => $this->second->id])->assertRedirect();

        $this->get(route('accounting.accounts.index'))->assertOk()->assertSee('Zakat payable')->assertDontSee('Table tax');
    });

    it('refuses a company the user does not belong to, even inside the tenant', function () {
        $stranger = app(CreateCompany::class)->handle('Hidden', null, 'KW');
        restoreContext($this->user);

        $this->post(route('company.switch'), ['company_id' => $stranger->id])->assertNotFound();
    });

    it('refuses another tenant\'s company', function () {
        ['company' => $foreign] = bootstrapTenant('o@example.com', 'Other');
        $this->actingAs($this->user);

        $this->post(route('company.switch'), ['company_id' => $foreign->id])->assertNotFound();
    });

    it('validates the id', function () {
        $this->post(route('company.switch'), ['company_id' => 'abc'])->assertSessionHasErrors('company_id');
    });

    it('shows the switcher in the navigation with each company and country', function () {
        $this->get(route('dashboard'))->assertOk()->assertSee('Riyadh Co')->assertSee('(SA)')->assertSee('(EG)');
    });

    it('supplies the composer with companies and the active one', function () {
        $this->actingAs($this->user);
        $context = app(TenantContext::class);
        $context->setTenant($this->user->tenant);
        $context->setCompany($this->first);

        $view = view('layouts.topbar');
        app(CompanySwitcherComposer::class)->compose($view);

        expect($view->getData()['switcherCompanies'])->toHaveCount(2)->and($view->getData()['activeCompany']->id)->toBe($this->first->id);
    });

    it('gives the composer nothing for a guest', function () {
        auth()->logout();
        app(TenantContext::class)->clear();
        $view = view('layouts.topbar');
        app(CompanySwitcherComposer::class)->compose($view);

        expect($view->getData()['switcherCompanies'])->toHaveCount(0)->and($view->getData()['activeCompany'])->toBeNull();
    });
});

it('registers its route and the tenantOwned macro', function () {
    expect(route('company.switch'))->toContain('/company/switch')
        ->and(Blueprint::hasMacro('tenantOwned'))->toBeTrue();
});
