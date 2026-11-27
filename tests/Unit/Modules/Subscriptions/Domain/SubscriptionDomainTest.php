<?php

use Carbon\CarbonImmutable;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Subscriptions\Domain\Exceptions\InvalidSubscriptionChange;
use Modules\Subscriptions\Domain\Exceptions\PlanLimitReached;
use Modules\Subscriptions\Domain\Services\SubscriptionPolicy;

covers(SubscriptionPolicy::class, SubscriptionStatus::class, AccessLevel::class, InvalidSubscriptionChange::class, PlanLimitReached::class);

describe('SubscriptionPolicy', function () {
    beforeEach(function () {
        $this->policy = new SubscriptionPolicy;
        $this->end = CarbonImmutable::parse('2026-06-30 12:00:00');
    });

    it('gives full access until the end', function (SubscriptionStatus $status) {
        expect($this->policy->accessLevel($status, $this->end, $this->end->subDay(), 7))->toBe(AccessLevel::Full)
            ->and($this->policy->accessLevel($status, $this->end, $this->end, 7))->toBe(AccessLevel::Full);
    })->with([SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Canceled]);

    it('goes read-only for the grace days after the end, then blocked', function (SubscriptionStatus $status) {
        expect($this->policy->accessLevel($status, $this->end, $this->end->addSecond(), 7))->toBe(AccessLevel::ReadOnly)
            ->and($this->policy->accessLevel($status, $this->end, $this->end->addDays(7), 7))->toBe(AccessLevel::ReadOnly)
            ->and($this->policy->accessLevel($status, $this->end, $this->end->addDays(7)->addSecond(), 7))->toBe(AccessLevel::Blocked);
    })->with([SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Expired]);

    it('never gives full access to an expired subscription, even before its stored end', function () {
        expect($this->policy->accessLevel(SubscriptionStatus::Expired, $this->end, $this->end->subDay(), 7))->toBe(AccessLevel::ReadOnly);
    });

    it('blocks immediately after the end when the grace period is zero', function () {
        expect($this->policy->accessLevel(SubscriptionStatus::Trialing, $this->end, $this->end->addSecond(), 0))->toBe(AccessLevel::Blocked);
    });

    it('treats an open-ended live subscription as always full and anything else as blocked', function () {
        expect($this->policy->accessLevel(SubscriptionStatus::Active, null, $this->end, 7))->toBe(AccessLevel::Full)
            ->and($this->policy->accessLevel(SubscriptionStatus::Expired, null, $this->end, 7))->toBe(AccessLevel::Blocked);
    });

    it('does not mutate the dates it is given', function () {
        $end = CarbonImmutable::parse('2026-06-30');
        $this->policy->accessLevel(SubscriptionStatus::Active, $end, $end->addDay(), 7);

        expect($end->toDateString())->toBe('2026-06-30');
    });
});

describe('SubscriptionStatus', function () {
    it('knows which statuses are live', function () {
        expect(SubscriptionStatus::Trialing->isLive())->toBeTrue()->and(SubscriptionStatus::Active->isLive())->toBeTrue()
            ->and(SubscriptionStatus::Expired->isLive())->toBeFalse()->and(SubscriptionStatus::Canceled->isLive())->toBeFalse();
    });

    it('allows only the documented transitions', function (SubscriptionStatus $from, SubscriptionStatus $to, bool $allowed) {
        expect($from->canTransitionTo($to))->toBe($allowed);
    })->with([
        [SubscriptionStatus::Trialing, SubscriptionStatus::Active, true],
        [SubscriptionStatus::Trialing, SubscriptionStatus::Expired, true],
        [SubscriptionStatus::Trialing, SubscriptionStatus::Canceled, true],
        [SubscriptionStatus::Trialing, SubscriptionStatus::Trialing, false],
        [SubscriptionStatus::Active, SubscriptionStatus::Expired, true],
        [SubscriptionStatus::Active, SubscriptionStatus::Canceled, true],
        [SubscriptionStatus::Active, SubscriptionStatus::Trialing, false],
        [SubscriptionStatus::Expired, SubscriptionStatus::Active, true],
        [SubscriptionStatus::Expired, SubscriptionStatus::Trialing, true],
        [SubscriptionStatus::Expired, SubscriptionStatus::Canceled, false],
        [SubscriptionStatus::Canceled, SubscriptionStatus::Active, true],
        [SubscriptionStatus::Canceled, SubscriptionStatus::Trialing, true],
        [SubscriptionStatus::Canceled, SubscriptionStatus::Expired, false],
    ]);

    it('throws on a forbidden transition and passes on an allowed one', function () {
        SubscriptionStatus::Trialing->assertCanTransitionTo(SubscriptionStatus::Active);

        expect(fn () => SubscriptionStatus::Active->assertCanTransitionTo(SubscriptionStatus::Trialing))->toThrow(InvalidSubscriptionChange::class);
    });

    it('has a badge tone and a label in both languages', function (string $locale) {
        app()->setLocale($locale);

        foreach (SubscriptionStatus::cases() as $status) {
            expect($status->tone())->toBeIn(['warning', 'success', 'danger', 'neutral'])->and($status->label())->not->toStartWith('subscriptions.');
        }
    })->with(['en', 'ar']);
});

describe('AccessLevel', function () {
    it('says what each level allows', function () {
        expect(AccessLevel::Full->allowsWrites())->toBeTrue()->and(AccessLevel::Full->allowsReads())->toBeTrue()
            ->and(AccessLevel::ReadOnly->allowsWrites())->toBeFalse()->and(AccessLevel::ReadOnly->allowsReads())->toBeTrue()
            ->and(AccessLevel::Blocked->allowsWrites())->toBeFalse()->and(AccessLevel::Blocked->allowsReads())->toBeFalse();
    });
});

describe('subscription exceptions', function () {
    it('translate into both languages', function (callable $make) {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $e = $make();

            expect($e->userMessage())->not->toStartWith('subscriptions.errors.')->not->toBeEmpty()->and($e->translationKey)->toStartWith('subscriptions.errors.');
        }
    })->with([
        'transition' => [fn () => InvalidSubscriptionChange::transition(SubscriptionStatus::Active, SubscriptionStatus::Trialing)],
        'already live' => [fn () => InvalidSubscriptionChange::alreadyLive()],
        'not trialing' => [fn () => InvalidSubscriptionChange::notTrialing()],
        'plan inactive' => [fn () => InvalidSubscriptionChange::planInactive()],
        'invalid days' => [fn () => InvalidSubscriptionChange::invalidDays()],
        'no subscription' => [fn () => InvalidSubscriptionChange::noSubscription()],
        'user limit' => [fn () => PlanLimitReached::users(3)],
        'company limit' => [fn () => PlanLimitReached::companies(1)],
        'no access' => [fn () => PlanLimitReached::noAccess()],
    ]);

    it('puts the limit into the message', function () {
        app()->setLocale('en');

        expect(PlanLimitReached::users(3)->userMessage())->toContain('3')->and(PlanLimitReached::companies(1)->userMessage())->toContain('1');
    });
});
