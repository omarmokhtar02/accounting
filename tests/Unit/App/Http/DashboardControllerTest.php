<?php

use App\Http\Controllers\DashboardController;
use Modules\Subscriptions\Application\GrantPlan;
use Modules\Subscriptions\Domain\Models\Plan;

covers(DashboardController::class);

beforeEach(function () {
    $this->owner = bootstrapTenant()['user'];
});

it('greets the user, names the company and offers the quick actions they may use', function () {
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('Welcome, Owner')->assertSee('Acme')
        ->assertSee('Journal Entries', false)->assertSee(route('accounting.imports.index'), false)
        ->assertSee('Trial balance');
});

it('lists every plan the company runs', function () {
    app(GrantPlan::class)->handle($this->owner->tenant_id, Plan::where('code', 'enterprise')->firstOrFail());
    restoreContext($this->owner);

    $this->get(route('dashboard'))->assertOk()->assertSee('Business')->assertSee('Enterprise');
});

it('hides the actions a viewer may not use', function () {
    restoreContext($this->owner);
    actingAsRole('viewer');

    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('accounting.imports.index'), false)
        ->assertDontSee(route('accounting.journal-entries.create'), false)->assertSee('Trial balance');
});

it('renders in Arabic, right to left, with the sidebar', function () {
    $this->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertOk()
        ->assertSee('أهلاً، Owner')->assertSee('dir="rtl"', false)->assertSee('id="sidebar"', false);
});

it('shows the trial banner with the days left', function () {
    $this->get(route('dashboard'))->assertOk()->assertSee('14');
});
