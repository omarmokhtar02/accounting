<?php

use Illuminate\Console\Scheduling\Schedule;
use Modules\Shared\Application\PlatformSettings;
use Modules\Shared\Domain\Contracts\TrialStarter;
use Modules\Shared\Domain\Contracts\UsageLimits;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Application\ExtendTrial;
use Modules\Subscriptions\Application\StartTrial;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Infrastructure\PlanUsageLimits;
use Modules\Subscriptions\Presentation\Console\ExpireSubscriptionsCommand;
use Modules\Subscriptions\Presentation\Controllers\BillingController;
use Modules\Subscriptions\Presentation\Middleware\EnforceSubscription;
use Modules\Subscriptions\Presentation\SubscriptionBannerComposer;
use Modules\Subscriptions\Providers\SubscriptionsServiceProvider;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(PlanUsageLimits::class, EnforceSubscription::class, BillingController::class, SubscriptionBannerComposer::class,
    ExpireSubscriptionsCommand::class, SubscriptionsServiceProvider::class);

function sub(object $user): Subscription
{
    return Subscription::where('tenant_id', $user->tenant_id)->firstOrFail();
}

describe('PlanUsageLimits', function () {
    it('lets a tenant add users and companies up to its plan', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['max_users' => 3, 'max_companies' => 2]);
        $limits = app(PlanUsageLimits::class);

        $limits->assertCanAddUser($user->tenant_id);   // 1 of 3
        $limits->assertCanAddCompany($user->tenant_id); // 1 of 2

        expect(true)->toBeTrue();
    });

    it('stops at the user limit with a clear, translated message', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['max_users' => 2]);
        bootstrapTenantUser();

        expect(fn () => app(PlanUsageLimits::class)->assertCanAddUser($user->tenant_id))->toThrow(PlanLimitReached::class);

        try {
            app(PlanUsageLimits::class)->assertCanAddUser($user->tenant_id);
        } catch (PlanLimitReached $e) {
            expect($e->userMessage())->toContain('2');
        }
    });

    it('stops at the company limit', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['max_companies' => 1]);

        app(PlanUsageLimits::class)->assertCanAddCompany($user->tenant_id);
    })->throws(PlanLimitReached::class);

    it('treats null limits as unlimited', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['max_users' => null, 'max_companies' => null]);

        foreach (range(1, 12) as $_) {
            bootstrapTenantUser();
        }

        app(PlanUsageLimits::class)->assertCanAddUser($user->tenant_id);

        expect(true)->toBeTrue();
    });

    it('refuses a tenant without any subscription', function () {
        app(PlanUsageLimits::class)->assertCanAddUser(Tenant::factory()->create()->id);
    })->throws(PlanLimitReached::class);

    it('is what the app uses to gate user and company creation', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['max_users' => 1, 'max_companies' => 1]);

        $this->post(route('admin.users.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'role' => 'viewer', 'company_ids' => [$user->companies()->first()->id],
        ])->assertSessionHasErrors('plan');

        $this->post(route('localization.companies.store'), ['name' => 'Second', 'country_code' => 'SA'])->assertSessionHasErrors('plan');
    });
});

describe('EnforceSubscription', function () {
    beforeEach(function () {
        ['user' => $this->user] = bootstrapTenant();
    });

    it('lets a tenant on trial use everything', function () {
        $this->get(route('accounting.accounts.index'))->assertOk();
        $this->post(route('accounting.accounts.store'), ['code' => '9950', 'name' => 'X', 'type' => 'asset'])->assertRedirect(route('accounting.accounts.index'));
    });

    it('is read-only during the grace period: reads pass, writes go to billing', function () {
        sub($this->user)->update(['trial_ends_at' => now()->subDays(2)]);

        $this->get(route('accounting.accounts.index'))->assertOk();
        $this->post(route('accounting.accounts.store'), ['code' => '9951', 'name' => 'X', 'type' => 'asset'])
            ->assertRedirect(route('billing.show'))->assertSessionHas('billing_notice', 'read_only');
    });

    it('blocks everything except billing after the grace period', function () {
        sub($this->user)->update(['trial_ends_at' => now()->subDays(30)]);

        $this->get(route('accounting.accounts.index'))->assertRedirect(route('billing.show'))->assertSessionHas('billing_notice', 'blocked');
        $this->get(route('dashboard'))->assertRedirect(route('billing.show'));
        $this->get(route('billing.show'))->assertOk();
    });

    it('follows the grace period the admin configures', function () {
        sub($this->user)->update(['trial_ends_at' => now()->subDays(30)]);
        app(PlatformSettings::class)->set('subscription.readonly_grace_days', 60);

        $this->get(route('accounting.accounts.index'))->assertOk();
    });

    it('restores access as soon as the admin extends the trial or activates a plan', function () {
        sub($this->user)->update(['trial_ends_at' => now()->subDays(30), 'status' => SubscriptionStatus::Expired]);
        $this->get(route('accounting.accounts.index'))->assertRedirect(route('billing.show'));

        app(ExtendTrial::class)->handle(sub($this->user), 14);
        $this->get(route('accounting.accounts.index'))->assertOk();

        app(ActivateSubscription::class)->handle(sub($this->user), Plan::where('code', 'business')->first());
        $this->get(route('accounting.accounts.index'))->assertOk();
    });

    it('is applied to every business route group', function () {
        foreach (['accounting.accounts.index', 'admin.users.index', 'localization.companies.index', 'dashboard'] as $name) {
            expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('subscribed');
        }
    });
});

describe('billing page', function () {
    it('shows plan, status, remaining trial days, usage and the available plans', function () {
        ['user' => $user] = bootstrapTenant();

        $this->get(route('billing.show'))->assertOk()
            ->assertSee('Business')->assertSee('Trial')->assertSee('14 day')->assertSee('1 / 10')->assertSee('Starter')->assertSee('Enterprise');
    });

    it('shows the admin-configured instructions in the user language', function () {
        bootstrapTenant();
        app(PlatformSettings::class)->set('billing.instructions_en', 'Pay by transfer to ACC-123.');
        app(PlatformSettings::class)->set('billing.contact_email', 'sales@example.com');

        $this->get(route('billing.show'))->assertSee('Pay by transfer to ACC-123.')->assertSee('sales@example.com');
        $this->withSession(['locale' => 'ar'])->get(route('billing.show'))->assertSee('تواصل معنا لتفعيل اشتراكك.');
    });

    it('shows a notice after being redirected', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['trial_ends_at' => now()->subDays(30)]);

        $this->followingRedirects()->get(route('accounting.accounts.index'))->assertSee('Please subscribe to continue');
    });

    it('requires login', function () {
        $this->get(route('billing.show'))->assertRedirect(route('login'));
    });
});

describe('trial banner', function () {
    it('shows the days left while on trial', function () {
        bootstrapTenant();

        $this->get(route('dashboard'))->assertSee('Free trial: 14 day(s) left.');
    });

    it('shows the read-only and blocked states', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['trial_ends_at' => now()->subDays(2)]);

        $this->get(route('dashboard'))->assertSee('read-only');
    });

    it('shows nothing for a paying tenant', function () {
        ['user' => $user] = bootstrapTenant();
        app(ActivateSubscription::class)->handle(sub($user), Plan::where('code', 'business')->first());

        $this->get(route('dashboard'))->assertDontSee('Free trial');
    });

    it('is Arabic in the Arabic UI', function () {
        bootstrapTenant();

        $this->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertSee('فترة تجريبية مجانية');
    });

    it('composes nothing without a tenant', function () {
        auth()->logout();
        app(TenantContext::class)->clear();
        $view = view('layouts.app');

        app(SubscriptionBannerComposer::class)->compose($view);

        expect($view->getData()['subscriptionBanner'])->toBeNull();
    });
});

describe('expiry command and provider', function () {
    it('runs from the console and reports the count', function () {
        ['user' => $user] = bootstrapTenant();
        sub($user)->update(['trial_ends_at' => now()->subHour()]);

        $this->artisan('subscriptions:expire')->expectsOutput('Expired 1 subscription(s).')->assertSuccessful();

        expect(sub($user)->status)->toBe(SubscriptionStatus::Expired);
    });

    it('binds the shared contracts to the subscription implementations', function () {
        expect(app(TrialStarter::class))->toBeInstanceOf(StartTrial::class)->and(app(UsageLimits::class))->toBeInstanceOf(PlanUsageLimits::class);
    });

    it('registers the billing route, views and the hourly expiry schedule', function () {
        expect(route('billing.show'))->toContain('/billing')->and(view()->exists('subscriptions::billing.show'))->toBeTrue();

        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'subscriptions:expire'));

        expect($scheduled)->toBeTrue();
    });
});
