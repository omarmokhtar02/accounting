<?php

use Modules\Platform\Presentation\Controllers\TenantController;
use Modules\Subscriptions\Application\GrantPlan;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Tenancy\Domain\Models\Tenant;

covers(TenantController::class);

beforeEach(function () {
    ['user' => $user] = bootstrapTenant('multi@example.com', 'Multi Co');
    $this->tenant = Tenant::findOrFail($user->tenant_id);
    $this->enterprise = Plan::where('code', 'enterprise')->firstOrFail();
    $this->trial = Subscription::where('tenant_id', $this->tenant->id)->firstOrFail();
    actingAsAdmin();
});

it('adds a plan from the tenant page', function () {
    $this->post(route('platform.subscriptions.grant-plan', $this->tenant), ['plan_id' => $this->enterprise->id, 'periods' => 2])
        ->assertSessionHas('status', 'Plan added.');

    expect(Subscription::where('tenant_id', $this->tenant->id)->where('plan_id', $this->enterprise->id)->where('status', 'active')->exists())->toBeTrue();
});

it('gives access to all plans in one click', function () {
    $this->post(route('platform.subscriptions.grant-all', $this->tenant), ['periods' => 1])->assertSessionHas('status', 'All plans added.');

    expect(Subscription::where('tenant_id', $this->tenant->id)->whereIn('status', ['trialing', 'active'])->count())->toBe(Plan::where('is_active', true)->count());
});

it('validates the grant forms', function () {
    $this->post(route('platform.subscriptions.grant-plan', $this->tenant), ['plan_id' => 9999, 'periods' => 1])->assertSessionHasErrors('plan_id');
    $this->post(route('platform.subscriptions.grant-plan', $this->tenant), ['plan_id' => $this->enterprise->id, 'periods' => 0])->assertSessionHasErrors('periods');
    $this->post(route('platform.subscriptions.grant-all', $this->tenant), ['periods' => 'x'])->assertSessionHasErrors('periods');
});

it('shows one card per plan with its own actions', function () {
    app(GrantPlan::class)->handle($this->tenant->id, $this->enterprise);

    $page = $this->get(route('platform.tenants.show', $this->tenant))->assertOk();

    $page->assertSee('data-subscription="'.$this->trial->id.'"', false)
        ->assertSee('Business')->assertSee('Enterprise')->assertSee('Give access to all plans')->assertSee('Add a plan')
        ->assertSee('name="subscription_id" value="'.$this->trial->id.'"', false);
});

it('acts on the subscription named in the form, not just the main one', function () {
    $granted = app(GrantPlan::class)->handle($this->tenant->id, $this->enterprise);

    $this->post(route('platform.subscriptions.cancel', $this->tenant), ['subscription_id' => $granted->id])->assertSessionHas('status');

    expect($granted->fresh()->status->value)->toBe('canceled')->and($this->trial->fresh()->status->value)->toBe('trialing');
});

it('refuses to touch another company\'s subscription', function () {
    bootstrapTenant('other@example.com', 'Other Co');
    $foreign = Subscription::where('tenant_id', '!=', $this->tenant->id)->firstOrFail();
    actingAsAdmin();

    $this->post(route('platform.subscriptions.cancel', $this->tenant), ['subscription_id' => $foreign->id])->assertNotFound();

    expect($foreign->fresh()->status->value)->toBe('trialing');
});

it('lists every live plan of a company in the customer list', function () {
    app(GrantPlan::class)->handle($this->tenant->id, $this->enterprise);

    $this->get(route('platform.tenants.index'))->assertOk()->assertSee('Business')->assertSee('Enterprise');
});

it('needs the subscriptions permission to grant plans', function () {
    actingAsAdmin('viewer');

    $this->post(route('platform.subscriptions.grant-plan', $this->tenant), ['plan_id' => $this->enterprise->id, 'periods' => 1])->assertForbidden();
    $this->post(route('platform.subscriptions.grant-all', $this->tenant), ['periods' => 1])->assertForbidden();
});
