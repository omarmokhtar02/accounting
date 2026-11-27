<?php

namespace Modules\Subscriptions\Application;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Services\SubscriptionPolicy;

/**
 * Answers, for a tenant, "what may they do right now?". A tenant can hold several plans at once, so the
 * answer is the BEST of its subscriptions: one running plan is enough for full access, and the limits
 * (users, companies) are the most generous of the plans that grant it.
 */
final class SubscriptionAccess
{
    public function __construct(
        private readonly SubscriptionPolicy $policy,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * Every subscription of the tenant, best first: highest access level, then open-ended or latest end.
     *
     * @return Collection<int, Subscription>
     */
    public function allFor(int $tenantId, ?CarbonInterface $now = null): Collection
    {
        $now ??= now();

        return Subscription::query()->with('plan')->where('tenant_id', $tenantId)->get()
            ->sortBy([
                fn (Subscription $a, Subscription $b) => $this->rank($this->level($b, $now)) <=> $this->rank($this->level($a, $now)),
                fn (Subscription $a, Subscription $b) => $this->endsAtSortKey($b) <=> $this->endsAtSortKey($a),
                fn (Subscription $a, Subscription $b) => $b->id <=> $a->id,
            ])
            ->values();
    }

    /** The subscription that decides what the tenant can do (the best one), or null when there is none. */
    public function currentFor(int $tenantId): ?Subscription
    {
        return $this->allFor($tenantId)->first();
    }

    public function levelFor(int $tenantId, ?CarbonInterface $now = null): AccessLevel
    {
        $current = $this->allFor($tenantId, $now)->first();

        return $current === null ? AccessLevel::Blocked : $this->level($current, $now ?? now());
    }

    /**
     * Limits that apply now: the most generous among the subscriptions with full access (null = unlimited).
     * Without any full-access plan, the best subscription's own limits apply.
     *
     * @return array{max_users: int|null, max_companies: int|null}|null null when the tenant has no subscription
     */
    public function limitsFor(int $tenantId): ?array
    {
        $all = $this->allFor($tenantId);

        if ($all->isEmpty()) {
            return null;
        }

        $full = $all->filter(fn (Subscription $s) => $this->level($s, now()) === AccessLevel::Full);
        $source = $full->isEmpty() ? collect([$all->first()]) : $full;

        return [
            'max_users' => $this->mostGenerous($source->pluck('max_users')),
            'max_companies' => $this->mostGenerous($source->pluck('max_companies')),
        ];
    }

    private function level(Subscription $subscription, CarbonInterface $now): AccessLevel
    {
        return $this->policy->accessLevel(
            $subscription->status,
            $subscription->accessEndsAt(),
            $now,
            $this->settings->int('subscription.readonly_grace_days'),
        );
    }

    private function rank(AccessLevel $level): int
    {
        return match ($level) {
            AccessLevel::Full => 2,
            AccessLevel::ReadOnly => 1,
            AccessLevel::Blocked => 0,
        };
    }

    /** Open-ended subscriptions sort as the furthest end. */
    private function endsAtSortKey(Subscription $subscription): int
    {
        return $subscription->accessEndsAt()?->getTimestamp() ?? PHP_INT_MAX;
    }

    /**
     * @param  Collection<int, int|null>  $limits
     */
    private function mostGenerous(Collection $limits): ?int
    {
        return $limits->contains(null) ? null : $limits->max();
    }
}
