<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\RegisterTenant;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Application\ActivateSubscription;
use Modules\Subscriptions\Application\CancelSubscription;
use Modules\Subscriptions\Application\ChangePlan;
use Modules\Subscriptions\Application\ExpireDueSubscriptions;
use Modules\Subscriptions\Application\ExtendTrial;
use Modules\Subscriptions\Application\RecordPayment;
use Modules\Subscriptions\Application\RecordsSubscriptionEvents;
use Modules\Subscriptions\Application\StartTrial;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Models\SubscriptionEvent;
use Modules\Subscriptions\Domain\Models\SubscriptionPayment;
use Modules\Tenancy\Domain\Models\Tenant;

covers(StartTrial::class, ActivateSubscription::class, ExtendTrial::class, ChangePlan::class, CancelSubscription::class,
    RecordPayment::class, ExpireDueSubscriptions::class, SubscriptionAccess::class, RecordsSubscriptionEvents::class,
    Plan::class, Subscription::class, SubscriptionEvent::class, SubscriptionPayment::class);

function tenantSubscription(object $user): Subscription
{
    return Subscription::where('tenant_id', $user->tenant_id)->firstOrFail();
}

function plan(string $code): Plan
{
    return Plan::where('code', $code)->firstOrFail();
}

describe('StartTrial', function () {
    it('starts a 14 day trial on the default plan when a tenant registers', function () {
        $user = app(RegisterTenant::class)->handle('O', 'o@example.com', Hash::make('p'), 'Acme', 'EG', 'en');
        $subscription = tenantSubscription($user);

        expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
            ->and($subscription->plan->code)->toBe('business')
            ->and($subscription->trial_ends_at->toDateString())->toBe(now()->addDays(14)->toDateString())
            ->and($subscription->daysLeft())->toBe(14)
            ->and($subscription->max_users)->toBe(10)->and($subscription->max_companies)->toBe(5)
            ->and($subscription->events->pluck('type')->all())->toBe(['trial_started']);
    });

    it('follows the trial length the admin configures', function () {
        app(PlatformSettings::class)->set('trial.days', 30);
        $user = app(RegisterTenant::class)->handle('O', 'o@example.com', Hash::make('p'), 'Acme', 'EG', 'en');

        expect(tenantSubscription($user)->daysLeft())->toBe(30);
    });

    it('lets a plan override the platform default trial length', function () {
        plan('business')->update(['trial_days' => 5]);
        $user = app(RegisterTenant::class)->handle('O', 'o@example.com', Hash::make('p'), 'Acme', 'EG', 'en');

        expect(tenantSubscription($user)->daysLeft())->toBe(5);
    });

    it('uses the plan the admin selected for trials, falling back to the first active plan', function () {
        app(PlatformSettings::class)->set('trial.plan_code', 'starter');
        $a = app(RegisterTenant::class)->handle('A', 'a@example.com', Hash::make('p'), 'A', 'EG', 'en');

        expect(tenantSubscription($a)->plan->code)->toBe('starter')->and(tenantSubscription($a)->max_users)->toBe(3);

        plan('starter')->update(['is_active' => false]);
        $b = app(RegisterTenant::class)->handle('B', 'b@example.com', Hash::make('p'), 'B', 'EG', 'en');

        expect(tenantSubscription($b)->plan->code)->toBe('business');
    });

    it('is idempotent', function () {
        $user = app(RegisterTenant::class)->handle('O', 'o@example.com', Hash::make('p'), 'Acme', 'EG', 'en');
        app(StartTrial::class)->startFor($user->tenant_id);

        expect(Subscription::where('tenant_id', $user->tenant_id)->count())->toBe(1);
    });

    it('fails clearly when no plan is available', function () {
        Plan::query()->update(['is_active' => false]);
        $tenant = Tenant::factory()->create();

        app(StartTrial::class)->startFor($tenant->id);
    })->throws(InvalidSubscriptionChange::class);
});

describe('ActivateSubscription', function () {
    beforeEach(function () {
        ['user' => $this->user] = bootstrapTenant();
        $this->subscription = tenantSubscription($this->user);
    });

    it('turns a trial into a paid period and snapshots the plan', function () {
        plan('enterprise')->update(['price_minor' => 49900]);

        $active = app(ActivateSubscription::class)->handle($this->subscription, plan('enterprise'), 1, 7);

        expect($active->status)->toBe(SubscriptionStatus::Active)->and($active->plan->code)->toBe('enterprise')
            ->and($active->trial_ends_at)->toBeNull()
            ->and($active->current_period_ends_at->toDateString())->toBe(now()->addMonth()->toDateString())
            ->and($active->price_minor)->toBe(49900)->and($active->max_users)->toBeNull()
            ->and($active->events->last()->type)->toBe('activated')->and($active->events->last()->admin_user_id)->toBe(7);
    });

    it('supports several periods and yearly plans', function () {
        plan('starter')->update(['interval' => 'year']);

        $active = app(ActivateSubscription::class)->handle($this->subscription, plan('starter'), 2);

        expect($active->current_period_ends_at->toDateString())->toBe(now()->addYears(2)->toDateString());
    });

    it('extends from the current end when renewing early, so no paid days are lost', function () {
        $active = app(ActivateSubscription::class)->handle($this->subscription, plan('business'), 1);
        $firstEnd = $active->current_period_ends_at->copy();

        $renewed = app(ActivateSubscription::class)->handle($active, plan('business'), 1);

        expect($renewed->current_period_ends_at->toDateString())->toBe($firstEnd->addMonth()->toDateString())
            ->and($renewed->events->last()->type)->toBe('renewed');
    });

    it('reactivates an expired subscription from now', function () {
        $this->subscription->update(['status' => SubscriptionStatus::Expired, 'trial_ends_at' => now()->subDays(20)]);

        $active = app(ActivateSubscription::class)->handle($this->subscription, plan('business'));

        expect($active->status)->toBe(SubscriptionStatus::Active)->and($active->current_period_starts_at->isToday())->toBeTrue();
    });

    it('rejects an inactive plan and invalid periods', function () {
        plan('starter')->update(['is_active' => false]);

        expect(fn () => app(ActivateSubscription::class)->handle($this->subscription, plan('starter')))->toThrow(InvalidSubscriptionChange::class)
            ->and(fn () => app(ActivateSubscription::class)->handle($this->subscription, plan('business'), 0))->toThrow(InvalidSubscriptionChange::class);
    });

    it('refuses to reactivate an old subscription while another is live', function () {
        $this->subscription->update(['status' => SubscriptionStatus::Expired]);
        Subscription::create([
            'tenant_id' => $this->user->tenant_id, 'plan_id' => plan('business')->id, 'status' => 'trialing',
            'trial_ends_at' => now()->addDay(), 'currency' => 'USD',
        ]);

        app(ActivateSubscription::class)->handle($this->subscription, plan('business'));
    })->throws(InvalidSubscriptionChange::class);
});

describe('ExtendTrial', function () {
    beforeEach(function () {
        ['user' => $this->user] = bootstrapTenant();
        $this->subscription = tenantSubscription($this->user);
    });

    it('adds days to the remaining trial', function () {
        $end = $this->subscription->trial_ends_at->copy();

        $extended = app(ExtendTrial::class)->handle($this->subscription, 7, 'wants a demo', 3);

        expect($extended->trial_ends_at->toDateString())->toBe($end->addDays(7)->toDateString())
            ->and($extended->events->last()->type)->toBe('trial_extended')
            ->and($extended->events->last()->data['reason'])->toBe('wants a demo')->and($extended->events->last()->admin_user_id)->toBe(3);
    });

    it('reopens a lapsed trial from now', function () {
        $this->subscription->update(['status' => SubscriptionStatus::Expired, 'trial_ends_at' => now()->subDays(30)]);

        $reopened = app(ExtendTrial::class)->handle($this->subscription, 7);

        expect($reopened->status)->toBe(SubscriptionStatus::Trialing)->and($reopened->daysLeft())->toBe(7);
    });

    it('refuses paying customers and invalid day counts', function () {
        expect(fn () => app(ExtendTrial::class)->handle($this->subscription, 0))->toThrow(InvalidSubscriptionChange::class)
            ->and(fn () => app(ExtendTrial::class)->handle($this->subscription, 366))->toThrow(InvalidSubscriptionChange::class);

        $active = app(ActivateSubscription::class)->handle($this->subscription, plan('business'));

        expect(fn () => app(ExtendTrial::class)->handle($active, 7))->toThrow(InvalidSubscriptionChange::class);
    });
});

describe('ChangePlan', function () {
    it('takes the new price and limits but keeps the dates', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = tenantSubscription($user);
        $end = $subscription->trial_ends_at->copy();
        plan('starter')->update(['price_minor' => 1000]);

        $changed = app(ChangePlan::class)->handle($subscription, plan('starter'), 2);

        expect($changed->plan->code)->toBe('starter')->and($changed->max_users)->toBe(3)->and($changed->price_minor)->toBe(1000)
            ->and($changed->trial_ends_at->toIso8601String())->toBe($end->toIso8601String())
            ->and($changed->events->last()->type)->toBe('plan_changed');
    });

    it('refuses an inactive plan', function () {
        ['user' => $user] = bootstrapTenant();
        plan('starter')->update(['is_active' => false]);

        app(ChangePlan::class)->handle(tenantSubscription($user), plan('starter'));
    })->throws(InvalidSubscriptionChange::class);
});

describe('CancelSubscription', function () {
    it('cancels at the end of what was paid for', function () {
        ['user' => $user] = bootstrapTenant();
        $active = app(ActivateSubscription::class)->handle(tenantSubscription($user), plan('business'));

        $canceled = app(CancelSubscription::class)->handle($active, 'too expensive', 4);

        expect($canceled->status)->toBe(SubscriptionStatus::Canceled)->and($canceled->canceled_at)->not->toBeNull()
            ->and($canceled->current_period_ends_at->isFuture())->toBeTrue()
            ->and(app(SubscriptionAccess::class)->levelFor($user->tenant_id))->toBe(AccessLevel::Full)
            ->and($canceled->events->last()->data['reason'])->toBe('too expensive');
    });

    it('cannot cancel something already expired', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = tenantSubscription($user);
        $subscription->update(['status' => SubscriptionStatus::Expired]);

        app(CancelSubscription::class)->handle($subscription);
    })->throws(InvalidSubscriptionChange::class);
});

describe('RecordPayment', function () {
    beforeEach(function () {
        ['user' => $this->user] = bootstrapTenant();
        $this->subscription = tenantSubscription($this->user);
    });

    it('records a payment in the subscription currency and logs an event', function () {
        $payment = app(RecordPayment::class)->handle($this->subscription, 49900, 'bank_transfer', 'TRX-1', now(), 5);

        expect($payment->amount_minor)->toBe(49900)->and($payment->currency)->toBe('USD')->and($payment->recorded_by)->toBe(5)
            ->and($this->subscription->fresh()->events->last()->type)->toBe('payment_recorded');
    });

    it('is idempotent for the same method and reference', function () {
        $a = app(RecordPayment::class)->handle($this->subscription, 100, 'bank_transfer', 'TRX-2', now());
        $b = app(RecordPayment::class)->handle($this->subscription, 100, 'bank_transfer', 'TRX-2', now());

        expect($b->id)->toBe($a->id)->and(SubscriptionPayment::count())->toBe(1);
    });

    it('accepts payments without a reference', function () {
        app(RecordPayment::class)->handle($this->subscription, 100, 'cash', null, now());
        app(RecordPayment::class)->handle($this->subscription, 200, 'cash', null, now());

        expect(SubscriptionPayment::count())->toBe(2);
    });
});

describe('ExpireDueSubscriptions', function () {
    it('expires ended trials and ended paid periods, and leaves the rest', function () {
        ['user' => $trial] = bootstrapTenant('t@example.com', 'T');
        ['user' => $paid] = bootstrapTenant('p@example.com', 'P');
        ['user' => $fresh] = bootstrapTenant('f@example.com', 'F');

        tenantSubscription($trial)->update(['trial_ends_at' => now()->subHour()]);
        $active = app(ActivateSubscription::class)->handle(tenantSubscription($paid), plan('business'));
        $active->update(['current_period_ends_at' => now()->subMinute()]);

        expect(app(ExpireDueSubscriptions::class)->handle())->toBe(2)
            ->and(tenantSubscription($trial)->status)->toBe(SubscriptionStatus::Expired)
            ->and(tenantSubscription($paid)->status)->toBe(SubscriptionStatus::Expired)
            ->and(tenantSubscription($fresh)->status)->toBe(SubscriptionStatus::Trialing)
            ->and(tenantSubscription($trial)->events->last()->type)->toBe('expired');
    });

    it('is idempotent', function () {
        ['user' => $user] = bootstrapTenant();
        tenantSubscription($user)->update(['trial_ends_at' => now()->subHour()]);

        app(ExpireDueSubscriptions::class)->handle();

        expect(app(ExpireDueSubscriptions::class)->handle())->toBe(0)->and(SubscriptionEvent::where('type', 'expired')->count())->toBe(1);
    });

    it('does not expire a subscription that was extended meanwhile', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = tenantSubscription($user);
        $subscription->update(['trial_ends_at' => now()->subHour()]);
        app(ExtendTrial::class)->handle($subscription->fresh(), 5);

        expect(app(ExpireDueSubscriptions::class)->handle())->toBe(0);
    });
});

describe('SubscriptionAccess', function () {
    it('prefers the live subscription over older ones', function () {
        ['user' => $user] = bootstrapTenant();
        $old = tenantSubscription($user);
        $old->update(['status' => SubscriptionStatus::Expired]);
        $live = Subscription::create([
            'tenant_id' => $user->tenant_id, 'plan_id' => plan('business')->id, 'status' => 'trialing',
            'trial_ends_at' => now()->addDays(3), 'currency' => 'USD',
        ]);

        expect(app(SubscriptionAccess::class)->currentFor($user->tenant_id)->id)->toBe($live->id);
    });

    it('maps the clock to full, read-only and blocked using the configured grace days', function () {
        ['user' => $user] = bootstrapTenant();
        $access = app(SubscriptionAccess::class);
        $end = tenantSubscription($user)->trial_ends_at;

        expect($access->levelFor($user->tenant_id, $end->copy()->subDay()))->toBe(AccessLevel::Full)
            ->and($access->levelFor($user->tenant_id, $end->copy()->addDays(3)))->toBe(AccessLevel::ReadOnly)
            ->and($access->levelFor($user->tenant_id, $end->copy()->addDays(8)))->toBe(AccessLevel::Blocked);

        app(PlatformSettings::class)->set('subscription.readonly_grace_days', 30);

        expect($access->levelFor($user->tenant_id, $end->copy()->addDays(8)))->toBe(AccessLevel::ReadOnly);
    });

    it('blocks a tenant that has no subscription at all', function () {
        expect(app(SubscriptionAccess::class)->levelFor(Tenant::factory()->create()->id))->toBe(AccessLevel::Blocked)
            ->and(app(SubscriptionAccess::class)->currentFor(999999))->toBeNull();
    });
});

describe('models', function () {
    it('computes days left and the access end', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = tenantSubscription($user);

        expect($subscription->accessEndsAt()->toIso8601String())->toBe($subscription->trial_ends_at->toIso8601String())
            ->and($subscription->daysLeft(CarbonImmutable::parse($subscription->trial_ends_at)->addDays(2)))->toBe(0);

        $subscription->update(['trial_ends_at' => null, 'status' => SubscriptionStatus::Active, 'current_period_ends_at' => null]);

        expect($subscription->fresh()->accessEndsAt())->toBeNull()->and($subscription->fresh()->daysLeft())->toBeNull();
    });

    it('relates plans, subscriptions, events and payments', function () {
        ['user' => $user] = bootstrapTenant();
        $subscription = tenantSubscription($user);
        app(RecordPayment::class)->handle($subscription, 100, 'cash', null, now());

        expect($subscription->tenant->id)->toBe($user->tenant_id)->and($subscription->plan->subscriptions)->toHaveCount(1)
            ->and($subscription->payments)->toHaveCount(1)->and($subscription->payments->first()->subscription->id)->toBe($subscription->id)
            ->and($subscription->events->first()->subscription->id)->toBe($subscription->id);
    });

    it('localises plan names and describes limits', function () {
        $plan = plan('starter');
        $plan->update(['description_en' => 'Small teams', 'description_ar' => 'فرق صغيرة']);

        app()->setLocale('ar');
        expect($plan->localizedName())->toBe('الأساسية')->and($plan->localizedDescription())->toBe('فرق صغيرة');

        app()->setLocale('en');
        expect($plan->localizedName())->toBe('Starter')->and($plan->isUnlimitedUsers())->toBeFalse()
            ->and(plan('enterprise')->isUnlimitedUsers())->toBeTrue()->and(plan('enterprise')->isUnlimitedCompanies())->toBeTrue();
    });
});
