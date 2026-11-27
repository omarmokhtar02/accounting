<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Application\ChangePlan;
use Modules\Subscriptions\Application\GrantAllPlans;
use Modules\Subscriptions\Application\GrantPlan;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Infrastructure\PlanUsageLimits;

covers(GrantPlan::class, GrantAllPlans::class, PlanUsageLimits::class);

beforeEach(function () {
    ['user' => $this->user] = bootstrapTenant();
    $this->tenantId = $this->user->tenant_id;
    $this->business = Plan::where('code', 'business')->firstOrFail();
    $this->enterprise = Plan::where('code', 'enterprise')->firstOrFail();
});

describe('GrantPlan', function () {
    it('adds a plan next to the trial the company already has', function () {
        $subscription = app(GrantPlan::class)->handle($this->tenantId, $this->enterprise, 3, 99);

        expect($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->plan_id)->toBe($this->enterprise->id)
            ->and($subscription->current_period_ends_at->toDateString())->toBe(now()->addMonths(3)->toDateString())
            ->and($subscription->events->pluck('type')->all())->toBe(['granted'])
            ->and($subscription->events->first()->admin_user_id)->toBe(99)
            ->and(Subscription::where('tenant_id', $this->tenantId)->count())->toBe(2);
    });

    it('renews instead of duplicating a plan the company already runs', function () {
        $trial = Subscription::where('tenant_id', $this->tenantId)->firstOrFail();
        $granted = app(GrantPlan::class)->handle($this->tenantId, $this->business, 1);

        expect($granted->id)->toBe($trial->id)->and($granted->status)->toBe(SubscriptionStatus::Active)
            ->and(Subscription::where('tenant_id', $this->tenantId)->count())->toBe(1);
    });

    it('refuses an inactive plan and a zero period', function () {
        $this->enterprise->update(['is_active' => false]);

        expect(fn () => app(GrantPlan::class)->handle($this->tenantId, $this->enterprise))->toThrow(InvalidSubscriptionChange::class)
            ->and(fn () => app(GrantPlan::class)->handle($this->tenantId, $this->business, 0))->toThrow(InvalidSubscriptionChange::class);
    });
});

describe('GrantAllPlans', function () {
    it('gives the company every active plan, once each, however often it runs', function () {
        $count = app(GrantAllPlans::class)->handle($this->tenantId, 1);
        app(GrantAllPlans::class)->handle($this->tenantId, 1);

        $live = Subscription::where('tenant_id', $this->tenantId)->whereIn('status', ['trialing', 'active'])->get();

        expect($count)->toBe(Plan::where('is_active', true)->count())
            ->and($live->pluck('plan_id')->sort()->values()->all())->toBe(Plan::where('is_active', true)->pluck('id')->sort()->values()->all())
            ->and($live->every(fn ($s) => $s->status === SubscriptionStatus::Active))->toBeTrue();
    });

    it('skips plans an admin switched off', function () {
        $this->enterprise->update(['is_active' => false]);

        app(GrantAllPlans::class)->handle($this->tenantId);

        expect(Subscription::where('tenant_id', $this->tenantId)->where('plan_id', $this->enterprise->id)->exists())->toBeFalse();
    });
});

describe('access across several plans', function () {
    it('lists the best subscription first and uses it as the current one', function () {
        $trial = Subscription::where('tenant_id', $this->tenantId)->firstOrFail();
        $trial->update(['trial_ends_at' => now()->subDays(30)]); // long expired
        $granted = app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);
        $access = app(SubscriptionAccess::class);

        expect($access->allFor($this->tenantId)->pluck('id')->first())->toBe($granted->id)
            ->and($access->currentFor($this->tenantId)->id)->toBe($granted->id)
            ->and($access->levelFor($this->tenantId))->toBe(AccessLevel::Full);
    });

    it('stays fully open while any one plan is running, even if another expired', function () {
        app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);
        Subscription::where('tenant_id', $this->tenantId)->where('plan_id', $this->business->id)
            ->update(['status' => 'expired', 'trial_ends_at' => now()->subDays(60)]);

        expect(app(SubscriptionAccess::class)->levelFor($this->tenantId))->toBe(AccessLevel::Full);
    });

    it('prefers an open-ended plan over a dated one', function () {
        $dated = app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);
        $openEnded = Subscription::where('tenant_id', $this->tenantId)->where('plan_id', $this->business->id)->firstOrFail();
        $openEnded->update(['status' => 'active', 'trial_ends_at' => null, 'current_period_ends_at' => null]);

        expect(app(SubscriptionAccess::class)->currentFor($this->tenantId)->id)->toBe($openEnded->id)->and($dated->id)->not->toBe($openEnded->id);
    });

    it('has no access and no limits without any subscription', function () {
        Subscription::where('tenant_id', $this->tenantId)->delete();
        $access = app(SubscriptionAccess::class);

        expect($access->currentFor($this->tenantId))->toBeNull()
            ->and($access->levelFor($this->tenantId))->toBe(AccessLevel::Blocked)
            ->and($access->limitsFor($this->tenantId))->toBeNull();
    });
});

describe('limits across several plans', function () {
    it('takes the most generous limit, and unlimited wins', function () {
        $access = app(SubscriptionAccess::class);

        expect($access->limitsFor($this->tenantId))->toBe(['max_users' => 10, 'max_companies' => 5]);

        app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);

        expect($access->limitsFor($this->tenantId))->toBe(['max_users' => null, 'max_companies' => null]);
    });

    it('ignores plans that no longer give full access', function () {
        app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);
        Subscription::where('plan_id', $this->enterprise->id)->update(['status' => 'expired', 'current_period_ends_at' => now()->subDays(60)]);

        expect(app(SubscriptionAccess::class)->limitsFor($this->tenantId))->toBe(['max_users' => 10, 'max_companies' => 5]);
    });

    it('falls back to the best subscription when none gives full access', function () {
        Subscription::where('tenant_id', $this->tenantId)->update(['status' => 'expired', 'trial_ends_at' => now()->subDays(3)]);

        expect(app(SubscriptionAccess::class)->limitsFor($this->tenantId))->toBe(['max_users' => 10, 'max_companies' => 5]);
    });

    it('lets PlanUsageLimits use the combined limits', function () {
        Subscription::where('tenant_id', $this->tenantId)->update(['max_users' => 1, 'max_companies' => 1]);
        $limits = app(PlanUsageLimits::class);

        expect(fn () => $limits->assertCanAddUser($this->tenantId))->toThrow(PlanLimitReached::class)
            ->and(fn () => $limits->assertCanAddCompany($this->tenantId))->toThrow(PlanLimitReached::class);

        app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);

        $limits->assertCanAddUser($this->tenantId);
        $limits->assertCanAddCompany($this->tenantId);
        expect(true)->toBeTrue();
    });

    it('refuses everything without a subscription', function () {
        Subscription::where('tenant_id', $this->tenantId)->delete();

        app(PlanUsageLimits::class)->assertCanAddUser($this->tenantId);
    })->throws(PlanLimitReached::class);
});

describe('one live subscription per plan', function () {
    it('is enforced by the database', function () {
        $copy = Subscription::where('tenant_id', $this->tenantId)->firstOrFail()->replicate();
        $copy->save();
    })->throws(UniqueConstraintViolationException::class);

    it('stops ChangePlan from moving onto a plan the company already runs', function () {
        $granted = app(GrantPlan::class)->handle($this->tenantId, $this->enterprise);

        app(ChangePlan::class)->handle($granted, $this->business);
    })->throws(InvalidSubscriptionChange::class);

    it('stops reactivating onto a plan that is already running elsewhere', function () {
        $expired = Subscription::where('tenant_id', $this->tenantId)->firstOrFail();
        $expired->update(['status' => 'expired']);
        app(GrantPlan::class)->handle($this->tenantId, $this->business); // a new live business subscription

        app(ActivateSubscription::class)->handle($expired, $this->business);
    })->throws(InvalidSubscriptionChange::class);
});
