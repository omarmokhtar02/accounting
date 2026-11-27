<?php

use Illuminate\Support\Facades\Auth;
use Modules\Platform\Application\SaveAdminRole;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;
use Modules\Platform\Domain\Models\PlatformAuditLog;
use Modules\Platform\Presentation\Console\CreateAdminCommand;
use Modules\Platform\Presentation\Controllers\AdminRoleController;
use Modules\Platform\Presentation\Controllers\AdminUserController;
use Modules\Platform\Presentation\Controllers\AuditController;
use Modules\Platform\Presentation\Controllers\DashboardController;
use Modules\Platform\Presentation\Controllers\PlanController;
use Modules\Platform\Presentation\Controllers\SettingsController;
use Modules\Platform\Presentation\Controllers\TenantController;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use Modules\Tenancy\Domain\Models\Tenant;

covers(DashboardController::class, TenantController::class, PlanController::class, SettingsController::class,
    AdminUserController::class, AdminRoleController::class, AuditController::class, CreateAdminCommand::class);

function customer(string $name = 'Acme Co'): array
{
    ['user' => $user] = bootstrapTenant(strtolower(str_replace(' ', '', $name)).'@example.com', $name);

    return [Tenant::find($user->tenant_id), $user];
}

describe('dashboard', function () {
    it('shows the counters, revenue and lists', function () {
        customer('Trial Co');
        [$paying] = customer('Paying Co');
        Plan::where('code', 'business')->update(['price_minor' => 250000, 'currency' => 'EGP']);
        app(ActivateSubscription::class)->handle(Subscription::where('tenant_id', $paying->id)->first(), Plan::where('code', 'business')->first());

        actingAsAdmin();

        $this->get(route('platform.dashboard'))->assertOk()
            ->assertSee('data-stat="tenants">2<', false)->assertSee('data-stat="trialing">1<', false)->assertSee('data-stat="active">1<', false)
            ->assertSee('2,500.00 EGP')->assertSee('Trial Co')->assertSee('Paying Co');
    });

    it('says so when there is no revenue yet, in Arabic too', function () {
        actingAsAdmin();

        $this->get(route('platform.dashboard'))->assertSee('No paying customers yet');
        $this->withSession(['locale' => 'ar'])->get(route('platform.dashboard'))->assertSee('لا يوجد مشتركون بعد');
    });
});

describe('customers', function () {
    it('lists customers with plan and status and supports search and status filters', function () {
        customer('Alpha Co');
        [$beta] = customer('Beta Co');
        Subscription::where('tenant_id', $beta->id)->update(['status' => 'expired']);
        Tenant::factory()->create(['name' => 'Ghost Co']);
        actingAsAdmin();

        $this->get(route('platform.tenants.index'))->assertOk()->assertSee('Alpha Co')->assertSee('Beta Co')->assertSee('Ghost Co')->assertSee('Business');
        $this->get(route('platform.tenants.index', ['q' => 'alpha']))->assertSee('Alpha Co')->assertDontSee('Beta Co');
        $this->get(route('platform.tenants.index', ['status' => 'expired']))->assertSee('Beta Co')->assertDontSee('Alpha Co');
        $this->get(route('platform.tenants.index', ['status' => 'trialing']))->assertSee('Alpha Co')->assertDontSee('Beta Co');
        $this->get(route('platform.tenants.index', ['status' => 'none']))->assertSee('Ghost Co')->assertDontSee('Alpha Co');
    });

    it('shows a customer with subscription, access level, companies, users, history and payments', function () {
        [$tenant] = customer('Detail Co');
        actingAsAdmin();

        $this->get(route('platform.tenants.show', $tenant))->assertOk()
            ->assertSee('Detail Co')->assertSee('Full access')->assertSee('Trial started')->assertSee('detailco@example.com')->assertSee('Extend trial');
    });

    it('hides the management forms from admins who may only view', function () {
        [$tenant] = customer();
        actingAsAdmin('viewer');

        $this->get(route('platform.tenants.show', $tenant))->assertOk()->assertDontSee('Extend trial')->assertDontSee('Suspend customer');
    });

    it('suspends and reactivates, and a suspended customer is locked out', function () {
        [$tenant, $user] = customer();
        actingAsAdmin();

        $this->post(route('platform.tenants.suspend', $tenant), ['reason' => 'abuse'])->assertSessionHas('status');
        expect($tenant->fresh()->status)->toBe('suspended');

        $this->get(route('platform.tenants.show', $tenant))->assertSee('Reactivate customer');

        $this->post(route('platform.tenants.activate', $tenant))->assertSessionHas('status');
        expect($tenant->fresh()->status)->toBe('active');
    });

    it('extends a trial and shows the new date', function () {
        [$tenant] = customer();
        $before = Subscription::where('tenant_id', $tenant->id)->first()->trial_ends_at->copy();
        actingAsAdmin();

        $this->post(route('platform.subscriptions.extend-trial', $tenant), ['days' => 7, 'reason' => 'demo'])->assertSessionHas('status');

        expect(Subscription::where('tenant_id', $tenant->id)->first()->trial_ends_at->toDateString())->toBe($before->addDays(7)->toDateString());
    });

    it('validates the trial extension', function (array $input) {
        [$tenant] = customer();
        actingAsAdmin();

        $this->post(route('platform.subscriptions.extend-trial', $tenant), $input)->assertSessionHasErrors('days');
    })->with([[['days' => 0]], [['days' => 400]], [['days' => 'x']], [[]]]);

    it('activates a paid subscription and changes plans', function () {
        [$tenant] = customer();
        $enterprise = Plan::where('code', 'enterprise')->first();
        actingAsAdmin();

        $this->post(route('platform.subscriptions.activate', $tenant), ['plan_id' => $enterprise->id, 'periods' => 3])->assertSessionHas('status');
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        expect($subscription->status)->toBe(SubscriptionStatus::Active)->and($subscription->plan->code)->toBe('enterprise')
            ->and($subscription->current_period_ends_at->toDateString())->toBe(now()->addMonths(3)->toDateString());

        $this->post(route('platform.subscriptions.change-plan', $tenant), ['plan_id' => Plan::where('code', 'starter')->value('id')])->assertSessionHas('status');

        expect($subscription->fresh()->plan->code)->toBe('starter')->and($subscription->fresh()->max_users)->toBe(3);
    });

    it('shows a translated error for a refused subscription change', function () {
        [$tenant] = customer();
        actingAsAdmin();
        $starter = Plan::where('code', 'starter');
        $starter->update(['is_active' => false]);

        $this->post(route('platform.subscriptions.change-plan', $tenant), ['plan_id' => $starter->value('id')])->assertSessionHasErrors('subscription');
    });

    it('cancels a subscription', function () {
        [$tenant] = customer();
        actingAsAdmin();

        $this->post(route('platform.subscriptions.cancel', $tenant), ['reason' => 'asked'])->assertSessionHas('status');

        expect(Subscription::where('tenant_id', $tenant->id)->first()->status)->toBe(SubscriptionStatus::Canceled);
    });

    it('records payments in the subscription currency, idempotently', function () {
        [$tenant] = customer();
        Subscription::where('tenant_id', $tenant->id)->update(['currency' => 'KWD']);
        actingAsAdmin();
        $payload = ['amount' => '12.345', 'method' => 'bank_transfer', 'reference' => 'TRX-9', 'paid_at' => '2026-03-10'];

        $this->post(route('platform.subscriptions.payments', $tenant), $payload)->assertSessionHas('status');
        $this->post(route('platform.subscriptions.payments', $tenant), $payload);

        expect(SubscriptionPayment::count())->toBe(1)->and(SubscriptionPayment::first()->amount_minor)->toBe(12345)
            ->and(SubscriptionPayment::first()->currency)->toBe('KWD');

        $this->get(route('platform.tenants.show', $tenant))->assertSee('12.345')->assertSee('TRX-9');
    });

    it('rejects bad payment input including too many decimals for the currency', function (array $input, string $field) {
        [$tenant] = customer();
        actingAsAdmin();

        $this->post(route('platform.subscriptions.payments', $tenant), $input + ['amount' => '10', 'method' => 'cash', 'paid_at' => '2026-03-10'])->assertSessionHasErrors($field);
    })->with([
        'no amount' => [['amount' => ''], 'amount'],
        'text amount' => [['amount' => 'ten'], 'amount'],
        'three decimals in USD' => [['amount' => '10.123'], 'amount'],
        'bad method' => [['method' => 'crypto'], 'method'],
        'bad date' => [['paid_at' => 'someday'], 'paid_at'],
    ]);

    it('records the admin actions in the audit log', function () {
        [$tenant] = customer();
        $admin = actingAsAdmin();

        $this->post(route('platform.tenants.suspend', $tenant));

        expect(PlatformAuditLog::where('action', 'tenant_suspended')->first()->admin_user_id)->toBe($admin->id);
    });

    it('returns 404 for an unknown customer', function () {
        actingAsAdmin();

        $this->get(route('platform.tenants.show', 99999))->assertNotFound();
    });
});

describe('plans', function () {
    it('lists plans with subscriber counts', function () {
        customer();
        actingAsAdmin('finance');

        $this->get(route('platform.plans.index'))->assertOk()->assertSee('business')->assertSee('Enterprise')->assertSee('Unlimited');
    });

    it('creates a plan converting the price to minor units of its currency', function () {
        actingAsAdmin();

        $this->post(route('platform.plans.store'), [
            'code' => 'gold', 'name_en' => 'Gold', 'name_ar' => 'ذهبية', 'price' => '49.500', 'currency' => 'KWD', 'interval' => 'year',
            'trial_days' => '', 'max_users' => '25', 'max_companies' => '', 'is_active' => '1',
        ])->assertRedirect(route('platform.plans.index'));

        $plan = Plan::where('code', 'gold')->first();

        expect($plan->price_minor)->toBe(49500)->and($plan->interval)->toBe('year')->and($plan->max_users)->toBe(25)
            ->and($plan->max_companies)->toBeNull()->and($plan->trial_days)->toBeNull()->and($plan->is_active)->toBeTrue();
    });

    it('edits a plan and shows its current price', function () {
        $plan = Plan::where('code', 'starter')->first();
        $plan->update(['price_minor' => 1999]);
        actingAsAdmin();

        $this->get(route('platform.plans.edit', $plan))->assertOk()->assertSee('19.99');
        $this->put(route('platform.plans.update', $plan), [
            'code' => 'starter', 'name_en' => 'Starter', 'name_ar' => 'الأساسية', 'price' => '24.99', 'currency' => 'USD', 'interval' => 'month', 'max_users' => '5',
        ])->assertRedirect(route('platform.plans.index'));

        expect($plan->fresh()->price_minor)->toBe(2499)->and($plan->fresh()->max_users)->toBe(5)->and($plan->fresh()->is_active)->toBeFalse();
    });

    it('validates plans', function (array $input, string $field) {
        actingAsAdmin();

        $this->post(route('platform.plans.store'), $input + [
            'code' => 'zzz', 'name_en' => 'Z', 'name_ar' => 'ز', 'price' => '1', 'currency' => 'USD', 'interval' => 'month',
        ])->assertSessionHasErrors($field);
    })->with([
        'duplicate code' => [['code' => 'starter'], 'code'],
        'bad code' => [['code' => 'no spaces'], 'code'],
        'no english name' => [['name_en' => ''], 'name_en'],
        'unknown currency' => [['currency' => 'ZZZ'], 'currency'],
        'bad interval' => [['interval' => 'weekly'], 'interval'],
        'negative price' => [['price' => '-5'], 'price'],
        'zero users' => [['max_users' => '0'], 'max_users'],
    ]);

    it('renders the create form', function () {
        actingAsAdmin();

        $this->get(route('platform.plans.create'))->assertOk()->assertSee('Max users');
    });
});

describe('settings', function () {
    it('shows and saves the dynamic settings, which apply immediately', function () {
        actingAsAdmin();

        $this->get(route('platform.settings.edit'))->assertOk()->assertSee('Free trial length')->assertSee('value="14"', false);

        $this->put(route('platform.settings.update'), [
            'trial_days' => 21, 'trial_plan_code' => 'starter', 'subscription_readonly_grace_days' => 10,
            'billing_instructions_en' => 'Pay at the bank.', 'billing_instructions_ar' => 'ادفع في البنك.', 'billing_contact_email' => 'bill@example.com',
        ])->assertSessionHas('status');

        $settings = app(PlatformSettings::class);

        expect($settings->int('trial.days'))->toBe(21)->and($settings->string('trial.plan_code'))->toBe('starter')
            ->and($settings->int('subscription.readonly_grace_days'))->toBe(10)->and($settings->string('billing.instructions_ar'))->toBe('ادفع في البنك.');

        ['user' => $user] = bootstrapTenant('new@example.com', 'New Co');
        $this->actingAs($user);
        Auth::shouldUse('web');

        expect(Subscription::where('tenant_id', $user->tenant_id)->first()->daysLeft())->toBe(21);
    });

    it('validates the settings', function (array $input, string $field) {
        actingAsAdmin();

        $this->put(route('platform.settings.update'), $input + [
            'trial_days' => 14, 'trial_plan_code' => 'business', 'subscription_readonly_grace_days' => 7,
        ])->assertSessionHasErrors($field);
    })->with([
        'negative trial' => [['trial_days' => -1], 'trial_days'],
        'huge trial' => [['trial_days' => 1000], 'trial_days'],
        'unknown plan' => [['trial_plan_code' => 'nope'], 'trial_plan_code'],
        'bad grace' => [['subscription_readonly_grace_days' => 'x'], 'subscription_readonly_grace_days'],
        'bad email' => [['billing_contact_email' => 'nope'], 'billing_contact_email'],
    ]);
});

describe('admin users', function () {
    it('lists admins with role and last sign-in', function () {
        actingAsAdmin();
        makeAdmin('support', ['name' => 'Sam Support']);

        $this->get(route('platform.admins.index'))->assertOk()->assertSee('Sam Support')->assertSee('Support');
    });

    it('creates an admin', function () {
        actingAsAdmin();
        $role = AdminRole::where('key', 'finance')->first();

        $this->get(route('platform.admins.create'))->assertOk();
        $this->post(route('platform.admins.store'), [
            'name' => 'Fiona', 'email' => 'fiona@example.com', 'password' => 'a-long-enough-pass', 'admin_role_id' => $role->id, 'locale' => 'ar',
        ])->assertRedirect(route('platform.admins.index'));

        expect(AdminUser::where('email', 'fiona@example.com')->first()->role->key)->toBe('finance');
    });

    it('shows a translated error for a weak password and keeps the other input', function () {
        actingAsAdmin();

        $this->post(route('platform.admins.store'), [
            'name' => 'Fiona', 'email' => 'fiona@example.com', 'password' => 'short', 'admin_role_id' => AdminRole::first()->id, 'locale' => 'en',
        ])->assertSessionHasErrors('password')->assertSessionHasInput('name', 'Fiona');

        expect(AdminUser::where('email', 'fiona@example.com')->exists())->toBeFalse();
    });

    it('validates new admins', function (array $input, string $field) {
        actingAsAdmin();

        $this->post(route('platform.admins.store'), $input + [
            'name' => 'N', 'email' => 'n@example.com', 'password' => 'a-long-enough-pass', 'admin_role_id' => AdminRole::first()->id, 'locale' => 'en',
        ])->assertSessionHasErrors($field);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'bad email' => [['email' => 'nope'], 'email'],
        'bad role' => [['admin_role_id' => 99999], 'admin_role_id'],
        'bad locale' => [['locale' => 'fr'], 'locale'],
    ]);

    it('rejects a duplicate admin email', function () {
        $existing = makeAdmin('viewer', ['email' => 'dup@example.com']);
        actingAsAdmin();

        $this->post(route('platform.admins.store'), [
            'name' => 'N', 'email' => 'dup@example.com', 'password' => 'a-long-enough-pass', 'admin_role_id' => $existing->admin_role_id, 'locale' => 'en',
        ])->assertSessionHasErrors('email');
    });

    it('edits an admin: role, active flag and optional new password', function () {
        actingAsAdmin();
        $target = makeAdmin('viewer');
        $this->get(route('platform.admins.edit', $target))->assertOk()->assertSee($target->name);

        $this->put(route('platform.admins.update', $target), [
            'name' => 'Renamed', 'password' => '', 'admin_role_id' => AdminRole::where('key', 'support')->value('id'), 'locale' => 'ar', 'is_active' => '1',
        ])->assertRedirect(route('platform.admins.index'));

        expect($target->fresh()->name)->toBe('Renamed')->and($target->fresh()->role->key)->toBe('support')->and($target->fresh()->is_active)->toBeTrue();
    });

    it('refuses to deactivate or demote the last super admin and yourself', function () {
        $me = actingAsAdmin();

        $this->put(route('platform.admins.update', $me), [
            'name' => $me->name, 'admin_role_id' => $me->admin_role_id, 'locale' => 'en',
        ])->assertSessionHasErrors('admin');

        $this->put(route('platform.admins.update', $me), [
            'name' => $me->name, 'admin_role_id' => AdminRole::where('key', 'viewer')->value('id'), 'locale' => 'en', 'is_active' => '1',
        ])->assertSessionHasErrors('admin');

        expect($me->fresh()->is_active)->toBeTrue()->and($me->fresh()->role->key)->toBe('super_admin');
    });
});

describe('admin roles', function () {
    it('lists roles with permission and admin counts, locking super admin and built-ins', function () {
        actingAsAdmin();

        $this->get(route('platform.roles.index'))->assertOk()->assertSee('Super admin')->assertSee('Built-in')->assertSee('Finance');
    });

    it('creates a custom role with chosen permissions', function () {
        actingAsAdmin();

        $this->get(route('platform.roles.create'))->assertOk()->assertSee('View customers')->assertSee('Manage tax rates');
        $this->post(route('platform.roles.store'), ['name_en' => 'Auditors', 'name_ar' => 'المراجعون', 'permissions' => ['audit.view', 'tenants.view']])
            ->assertRedirect(route('platform.roles.index'));

        expect(AdminRole::where('key', 'auditors')->first()->permissions()->pluck('key')->sort()->values()->all())->toBe(['audit.view', 'tenants.view']);
    });

    it('edits a role and applies it to its admins immediately', function () {
        $admin = actingAsAdmin();
        $custom = app(SaveAdminRole::class)->create('Ops', 'عمليات', []);
        $ops = makeAdmin('viewer', ['admin_role_id' => $custom->id]);

        $this->get(route('platform.roles.edit', $custom))->assertOk()->assertSee('Manage plans and prices');
        $this->put(route('platform.roles.update', $custom), ['name_en' => 'Ops', 'name_ar' => 'عمليات', 'permissions' => ['plans.manage']])
            ->assertRedirect(route('platform.roles.index'));

        expect(AdminUser::find($ops->id)->hasPermission('plans.manage'))->toBeTrue();
    });

    it('refuses to edit super admin', function () {
        actingAsAdmin();
        $super = AdminRole::where('key', 'super_admin')->first();

        $this->put(route('platform.roles.update', $super), ['name_en' => 'X', 'name_ar' => 'X', 'permissions' => []])->assertSessionHasErrors('role');
    });

    it('deletes unused custom roles and refuses built-in or used ones', function () {
        actingAsAdmin();
        $custom = app(SaveAdminRole::class)->create('Temp', 'مؤقت', []);

        $this->delete(route('platform.roles.destroy', $custom))->assertRedirect(route('platform.roles.index'));
        expect(AdminRole::where('key', 'temp')->exists())->toBeFalse();

        $this->delete(route('platform.roles.destroy', AdminRole::where('key', 'viewer')->first()))->assertSessionHasErrors('role');
    });

    it('validates role names', function () {
        actingAsAdmin();

        $this->post(route('platform.roles.store'), ['name_en' => '', 'name_ar' => ''])->assertSessionHasErrors(['name_en', 'name_ar']);
    });
});

describe('audit log', function () {
    it('lists what admins did and filters by entity', function () {
        [$tenant] = customer();
        actingAsAdmin();
        $this->post(route('platform.tenants.suspend', $tenant), ['reason' => 'x']);
        $this->put(route('platform.settings.update'), ['trial_days' => 20, 'trial_plan_code' => 'business', 'subscription_readonly_grace_days' => 7]);

        $this->get(route('platform.audit.index'))->assertOk()->assertSee('tenant_suspended')->assertSee('settings_updated');
        $this->get(route('platform.audit.index', ['entity' => 'tenant']))->assertSee('tenant_suspended')->assertDontSee('settings_updated');
    });
});

describe('platform:create-admin', function () {
    it('creates the first super admin', function () {
        $this->artisan('platform:create-admin', ['email' => 'Root@Example.com', '--name' => 'Root', '--password' => 'a-very-long-password'])
            ->expectsOutputToContain('created')->assertSuccessful();

        $admin = AdminUser::where('email', 'root@example.com')->first();

        expect($admin->role->key)->toBe('super_admin')->and($admin->name)->toBe('Root');
    });

    it('refuses duplicates, unknown roles and weak passwords', function () {
        makeAdmin('viewer', ['email' => 'taken@example.com']);

        $this->artisan('platform:create-admin', ['email' => 'taken@example.com', '--password' => 'a-very-long-password'])->assertFailed();
        $this->artisan('platform:create-admin', ['email' => 'a@example.com', '--password' => 'a-very-long-password', '--role' => 'nope'])->assertFailed();
        $this->artisan('platform:create-admin', ['email' => 'b@example.com', '--password' => 'short'])->assertFailed();

        expect(AdminUser::where('email', 'b@example.com')->exists())->toBeFalse();
    });
});
