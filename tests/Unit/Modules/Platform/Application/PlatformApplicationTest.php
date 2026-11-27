<?php

use Modules\Platform\Application\AdminGuardrails;
use Modules\Platform\Application\PlatformStats;
use Modules\Platform\Application\RecordPlatformAudit;
use Modules\Platform\Application\SaveAdminRole;
use Modules\Platform\Application\SaveAdminUser;
use Modules\Platform\Application\SavePlan;
use Modules\Platform\Application\SetTenantStatus;
use Modules\Platform\Application\UpdatePlatformSettings;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminPermission;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;
use Modules\Platform\Domain\Models\PlatformAuditLog;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Tenancy\Domain\Models\Tenant;

covers(AdminGuardrails::class, PlatformStats::class, RecordPlatformAudit::class, SaveAdminRole::class, SaveAdminUser::class,
    SavePlan::class, SetTenantStatus::class, UpdatePlatformSettings::class, AdminOperationDenied::class,
    AdminPermission::class, AdminRole::class, AdminUser::class, PlatformAuditLog::class);

describe('admin models and the seeded catalogue', function () {
    it('ships four built-in roles and the permission catalogue', function () {
        expect(AdminRole::where('is_system', true)->pluck('key')->sort()->values()->all())->toBe(['finance', 'super_admin', 'support', 'viewer'])
            ->and(AdminPermission::count())->toBe(10)
            ->and(AdminRole::where('key', 'super_admin')->first()->permissions()->count())->toBe(10);
    });

    it('gives super admin everything and others only their permissions', function () {
        $super = makeAdmin('super_admin');
        $viewer = makeAdmin('viewer');
        $support = makeAdmin('support');

        expect($super->hasPermission('admins.manage'))->toBeTrue()->and($super->hasPermission('anything.at.all'))->toBeTrue()
            ->and($viewer->hasPermission('tenants.view'))->toBeTrue()->and($viewer->hasPermission('tenants.manage'))->toBeFalse()
            ->and($support->hasPermission('subscriptions.manage'))->toBeTrue()->and($support->hasPermission('plans.manage'))->toBeFalse()
            ->and($support->hasPermission('admins.manage'))->toBeFalse();
    });

    it('hashes passwords and hides secrets', function () {
        $admin = makeAdmin();

        expect($admin->password)->not->toBe('correct-horse-battery')->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue()
            ->and($admin->toArray())->not->toHaveKeys(['password', 'remember_token']);
    });

    it('relates roles, permissions, users and audit entries', function () {
        $admin = makeAdmin('finance');
        app(RecordPlatformAudit::class)->handle('x', 'thing', 1, [], $admin->id);

        $permission = AdminPermission::where('key', 'plans.manage')->first();

        expect($admin->role->isSuperAdmin())->toBeFalse()->and($admin->role->users->pluck('id'))->toContain($admin->id)
            ->and($permission->roles->pluck('key'))->toContain('finance')
            ->and(PlatformAuditLog::first()->admin->id)->toBe($admin->id);
    });

    it('localises role names', function () {
        $role = AdminRole::where('key', 'support')->first();

        app()->setLocale('ar');
        expect($role->localizedName())->toBe('الدعم الفني');

        app()->setLocale('en');
        expect($role->localizedName())->toBe('Support')->and($role->isSuperAdmin())->toBeFalse();
    });
});

describe('AdminOperationDenied', function () {
    it('translates every refusal into both languages', function (callable $make) {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $e = $make();

            expect($e->userMessage())->not->toStartWith('platform.errors.')->not->toBeEmpty();
        }
    })->with([
        [fn () => AdminOperationDenied::lastSuperAdmin()],
        [fn () => AdminOperationDenied::systemRole()],
        [fn () => AdminOperationDenied::roleInUse(3)],
        [fn () => AdminOperationDenied::cannotDeactivateSelf()],
        [fn () => AdminOperationDenied::weakPassword()],
    ]);

    it('mentions how many admins use the role', function () {
        app()->setLocale('en');

        expect(AdminOperationDenied::roleInUse(3)->userMessage())->toContain('3');
    });
});

describe('RecordPlatformAudit', function () {
    it('records the logged-in admin, action, entity and details', function () {
        $admin = actingAsAdmin();

        $log = app(RecordPlatformAudit::class)->handle('plan_updated', 'plan', 7, ['price' => 100]);

        expect($log->admin_user_id)->toBe($admin->id)->and($log->action)->toBe('plan_updated')->and($log->entity_type)->toBe('plan')
            ->and($log->entity_id)->toBe(7)->and($log->data)->toBe(['price' => 100])->and($log->occurred_at)->not->toBeNull();
    });

    it('records system actions without an admin and accepts an explicit actor', function () {
        $none = app(RecordPlatformAudit::class)->handle('cron', 'system');
        $explicit = app(RecordPlatformAudit::class)->handle('x', 'y', null, [], makeAdmin()->id);

        expect($none->admin_user_id)->toBeNull()->and($none->data)->toBeNull()->and($explicit->admin_user_id)->not->toBeNull();
    });
});

describe('AdminGuardrails', function () {
    it('refuses to remove the last active super admin but allows it when another exists', function () {
        $only = makeAdmin('super_admin');
        $guard = app(AdminGuardrails::class);

        expect(fn () => $guard->assertNotRemovingLastSuperAdmin($only))->toThrow(AdminOperationDenied::class);

        makeAdmin('super_admin');
        $guard->assertNotRemovingLastSuperAdmin($only);

        expect(true)->toBeTrue();
    });

    it('does not count inactive super admins and ignores non super admins', function () {
        $only = makeAdmin('super_admin');
        makeAdmin('super_admin', ['is_active' => false]);
        $guard = app(AdminGuardrails::class);

        expect(fn () => $guard->assertNotRemovingLastSuperAdmin($only))->toThrow(AdminOperationDenied::class);

        $guard->assertNotRemovingLastSuperAdmin(makeAdmin('viewer'));
        $guard->assertNotRemovingLastSuperAdmin(makeAdmin('super_admin', ['is_active' => false]));

        expect(true)->toBeTrue();
    });

    it('stops an admin from deactivating themselves', function () {
        $admin = makeAdmin();

        expect(fn () => app(AdminGuardrails::class)->assertNotSelf($admin, $admin->id))->toThrow(AdminOperationDenied::class);

        app(AdminGuardrails::class)->assertNotSelf($admin, $admin->id + 1);
        app(AdminGuardrails::class)->assertNotSelf($admin, null);

        expect(true)->toBeTrue();
    });
});

describe('SaveAdminUser', function () {
    it('creates an admin with a lowercased email, a role and an audit entry', function () {
        $admin = app(SaveAdminUser::class)->create('Sara', 'Sara@Example.COM', 'a-long-enough-pass', AdminRole::where('key', 'support')->first(), 'ar');

        expect($admin->email)->toBe('sara@example.com')->and($admin->role->key)->toBe('support')->and($admin->locale)->toBe('ar')
            ->and($admin->is_active)->toBeTrue()
            ->and(PlatformAuditLog::where('action', 'admin_created')->count())->toBe(1);
    });

    it('rejects short passwords on create and update', function () {
        $save = app(SaveAdminUser::class);

        expect(fn () => $save->create('X', 'x@example.com', 'short', AdminRole::first()))->toThrow(AdminOperationDenied::class);

        $admin = makeAdmin('viewer');

        expect(fn () => $save->update($admin, 'X', AdminRole::first(), true, 'short'))->toThrow(AdminOperationDenied::class);
    });

    it('updates name, role, activity and locale, keeping the password when none is given', function () {
        $admin = makeAdmin('viewer');
        $hash = $admin->password;

        $updated = app(SaveAdminUser::class)->update($admin, 'New Name', AdminRole::where('key', 'finance')->first(), false, null, 'ar');

        expect($updated->name)->toBe('New Name')->and($updated->role->key)->toBe('finance')->and($updated->is_active)->toBeFalse()
            ->and($updated->locale)->toBe('ar')->and($updated->password)->toBe($hash);
    });

    it('changes the password when a strong one is given and says so in the audit log', function () {
        $admin = makeAdmin('viewer');

        app(SaveAdminUser::class)->update($admin, $admin->name, $admin->role, true, 'a-brand-new-password');

        expect(Hash::check('a-brand-new-password', $admin->fresh()->password))->toBeTrue()
            ->and(PlatformAuditLog::where('action', 'admin_updated')->first()->data['password_changed'])->toBeTrue();
    });

    it('protects the last super admin from demotion and deactivation', function () {
        $only = makeAdmin('super_admin');
        $viewerRole = AdminRole::where('key', 'viewer')->first();

        expect(fn () => app(SaveAdminUser::class)->update($only, $only->name, $viewerRole, true))->toThrow(AdminOperationDenied::class)
            ->and(fn () => app(SaveAdminUser::class)->update($only, $only->name, $only->role, false))->toThrow(AdminOperationDenied::class);

        expect($only->fresh()->role->key)->toBe('super_admin')->and($only->fresh()->is_active)->toBeTrue();
    });

    it('stops you deactivating yourself even when another super admin exists', function () {
        $me = makeAdmin('super_admin');
        makeAdmin('super_admin');

        app(SaveAdminUser::class)->update($me, $me->name, $me->role, false, null, null, $me->id);
    })->throws(AdminOperationDenied::class);

    it('allows demoting a super admin when another remains', function () {
        $a = makeAdmin('super_admin');
        makeAdmin('super_admin');

        expect(app(SaveAdminUser::class)->update($a, $a->name, AdminRole::where('key', 'viewer')->first(), true)->role->key)->toBe('viewer');
    });
});

describe('SaveAdminRole', function () {
    it('creates a custom role with a unique key and known permissions only', function () {
        $role = app(SaveAdminRole::class)->create('Sales Team', 'فريق المبيعات', ['tenants.view', 'plans.manage', 'bogus.key']);
        $second = app(SaveAdminRole::class)->create('Sales Team', 'فريق المبيعات 2', []);

        expect($role->key)->toBe('sales_team')->and($second->key)->toBe('sales_team_2')->and($role->is_system)->toBeFalse()
            ->and($role->permissions()->pluck('key')->sort()->values()->all())->toBe(['plans.manage', 'tenants.view']);
    });

    it('updates names and replaces permissions, recording the change', function () {
        $role = app(SaveAdminRole::class)->create('Ops', 'عمليات', ['tenants.view']);

        $updated = app(SaveAdminRole::class)->update($role, 'Operations', 'العمليات', ['audit.view']);

        expect($updated->name_en)->toBe('Operations')->and($updated->permissions()->pluck('key')->all())->toBe(['audit.view'])
            ->and(PlatformAuditLog::where('action', 'admin_role_updated')->first()->data['from'])->toBe(['tenants.view']);
    });

    it('never edits super admin', function () {
        app(SaveAdminRole::class)->update(AdminRole::where('key', 'super_admin')->first(), 'X', 'X', []);
    })->throws(AdminOperationDenied::class);

    it('deletes an unused custom role but not a built-in or used one', function () {
        $custom = app(SaveAdminRole::class)->create('Temp', 'مؤقت', []);
        app(SaveAdminRole::class)->delete($custom);

        expect(AdminRole::where('key', 'temp')->exists())->toBeFalse()
            ->and(fn () => app(SaveAdminRole::class)->delete(AdminRole::where('key', 'viewer')->first()))->toThrow(AdminOperationDenied::class);

        $used = app(SaveAdminRole::class)->create('Used', 'مستخدم', []);
        makeAdmin('viewer', ['admin_role_id' => $used->id]);

        expect(fn () => app(SaveAdminRole::class)->delete($used))->toThrow(AdminOperationDenied::class);
    });

    it('applies new permissions immediately to the admins of that role', function () {
        $role = app(SaveAdminRole::class)->create('Ops', 'عمليات', []);
        $admin = makeAdmin('viewer', ['admin_role_id' => $role->id]);

        expect($admin->hasPermission('tenants.view'))->toBeFalse();

        app(SaveAdminRole::class)->update($role, 'Ops', 'عمليات', ['tenants.view']);

        expect(AdminUser::find($admin->id)->hasPermission('tenants.view'))->toBeTrue();
    });
});

describe('SetTenantStatus', function () {
    it('suspends and reactivates a tenant with an audit trail', function () {
        $tenant = Tenant::factory()->create();
        $action = app(SetTenantStatus::class);

        expect($action->suspend($tenant, 'non payment')->status)->toBe('suspended')
            ->and($action->activate($tenant)->status)->toBe('active')
            ->and(PlatformAuditLog::where('entity_type', 'tenant')->pluck('action')->sort()->values()->all())->toBe(['tenant_activated', 'tenant_suspended'])
            ->and(PlatformAuditLog::where('action', 'tenant_suspended')->first()->data['reason'])->toBe('non payment');
    });

    it('locks a suspended tenant out of the app', function () {
        ['user' => $user] = bootstrapTenant();
        app(SetTenantStatus::class)->suspend(Tenant::find($user->tenant_id));

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertForbidden();
    });
});

describe('SavePlan', function () {
    it('creates and updates plans, auditing the price and limit changes', function () {
        $plan = app(SavePlan::class)->handle(null, [
            'code' => 'gold', 'name_en' => 'Gold', 'name_ar' => 'ذهبية', 'price_minor' => 5000, 'currency' => 'USD', 'interval' => 'month', 'max_users' => 20, 'is_active' => true,
        ]);

        $updated = app(SavePlan::class)->handle($plan, ['price_minor' => 6000, 'max_users' => 50]);

        expect($updated->price_minor)->toBe(6000)->and($updated->max_users)->toBe(50)
            ->and(PlatformAuditLog::where('action', 'plan_created')->count())->toBe(1)
            ->and(PlatformAuditLog::where('action', 'plan_updated')->first()->data['from']['price_minor'])->toBe(5000);
    });

    it('does not change running subscriptions when a plan is edited', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = Subscription::where('tenant_id', $user->tenant_id)->first();
        $plan = $subscription->plan;

        app(SavePlan::class)->handle($plan, ['price_minor' => 99900, 'max_users' => 1]);

        expect($subscription->fresh()->max_users)->toBe(10)->and($subscription->fresh()->price_minor)->toBe(0);

        app(ActivateSubscription::class)->handle($subscription, $plan->fresh());

        expect($subscription->fresh()->max_users)->toBe(1)->and($subscription->fresh()->price_minor)->toBe(99900);
    });
});

describe('UpdatePlatformSettings', function () {
    it('saves the given settings and logs only what changed', function () {
        app(UpdatePlatformSettings::class)->handle(['trial_days' => 30, 'subscription_readonly_grace_days' => 7, 'billing_contact_email' => 'a@b.co']);

        $settings = app(PlatformSettings::class);
        $log = PlatformAuditLog::where('action', 'settings_updated')->first();

        expect($settings->int('trial.days'))->toBe(30)->and($settings->string('billing.contact_email'))->toBe('a@b.co')
            ->and($log->data)->toHaveKeys(['trial.days', 'billing.contact_email'])->not->toHaveKey('subscription.readonly_grace_days')
            ->and($log->data['trial.days'])->toBe(['from' => 14, 'to' => 30]);
    });

    it('logs nothing when nothing changed and ignores unknown fields', function () {
        app(UpdatePlatformSettings::class)->handle(['trial_days' => 14, 'nope' => 'x', 'trial_plan_code' => null]);

        expect(PlatformAuditLog::count())->toBe(0);
    });

    it('takes effect for the next sign-up immediately', function () {
        app(UpdatePlatformSettings::class)->handle(['trial_days' => 3]);
        ['user' => $user] = bootstrapTenant();

        expect(Subscription::where('tenant_id', $user->tenant_id)->first()->daysLeft())->toBe(3);
    });
});

describe('PlatformStats', function () {
    it('counts customers by subscription state', function () {
        bootstrapTenant('a@example.com', 'A');
        ['user' => $paid] = bootstrapTenant('b@example.com', 'B');
        ['user' => $expired] = bootstrapTenant('c@example.com', 'C');
        Tenant::factory()->create();

        app(ActivateSubscription::class)->handle(Subscription::where('tenant_id', $paid->tenant_id)->first(), Plan::where('code', 'business')->first());
        Subscription::where('tenant_id', $expired->tenant_id)->update(['status' => 'expired']);

        $stats = app(PlatformStats::class)->snapshot();

        expect($stats['tenants'])->toBe(['total' => 4, 'suspended' => 0, 'no_subscription' => 1])
            ->and($stats['subscriptions'])->toBe(['trialing' => 1, 'active' => 1, 'expired' => 1, 'canceled' => 0])
            ->and($stats['users'])->toBe(3)->and($stats['companies'])->toBe(3)->and($stats['signups_30d'])->toBe(4)
            ->and($stats['recent_signups'])->toHaveCount(4)->and($stats['recently_expired'])->toHaveCount(1);
    });

    it('counts suspended tenants', function () {
        app(SetTenantStatus::class)->suspend(Tenant::factory()->create());

        expect(app(PlatformStats::class)->snapshot()['tenants']['suspended'])->toBe(1);
    });

    it('lists trials ending within the warning window, soonest first', function () {
        ['user' => $soon] = bootstrapTenant('s@example.com', 'Soon');
        ['user' => $later] = bootstrapTenant('l@example.com', 'Later');
        Subscription::where('tenant_id', $soon->tenant_id)->update(['trial_ends_at' => now()->addDays(2)]);
        Subscription::where('tenant_id', $later->tenant_id)->update(['trial_ends_at' => now()->addDays(20)]);

        $ending = app(PlatformStats::class)->snapshot(7)['trials_ending'];

        expect($ending)->toHaveCount(1)->and($ending->first()->tenant->name)->toBe('Soon');
    });

    it('computes monthly recurring revenue per currency from paying subscriptions only', function () {
        ['user' => $monthly] = bootstrapTenant('m@example.com', 'M');
        ['user' => $yearly] = bootstrapTenant('y@example.com', 'Y');
        ['user' => $trial] = bootstrapTenant('t@example.com', 'T');
        Plan::where('code', 'starter')->update(['price_minor' => 10000, 'currency' => 'USD']);
        Plan::where('code', 'business')->update(['price_minor' => 120000, 'currency' => 'EGP', 'interval' => 'year']);

        app(ActivateSubscription::class)->handle(Subscription::where('tenant_id', $monthly->tenant_id)->first(), Plan::where('code', 'starter')->first());
        app(ActivateSubscription::class)->handle(Subscription::where('tenant_id', $yearly->tenant_id)->first(), Plan::where('code', 'business')->first());

        expect(app(PlatformStats::class)->snapshot()['mrr'])->toBe(['EGP' => 10000, 'USD' => 10000]);
    });

    it('has no revenue and no rows on an empty platform', function () {
        $stats = app(PlatformStats::class)->snapshot();

        expect($stats['mrr'])->toBe([])->and($stats['tenants']['total'])->toBe(0)->and($stats['trials_ending'])->toHaveCount(0);
    });
});
