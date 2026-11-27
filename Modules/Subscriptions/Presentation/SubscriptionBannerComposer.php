<?php

namespace Modules\Subscriptions\Presentation;

use Illuminate\View\View;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Feeds the app layout with the trial / expiry banner so Blade never queries or computes anything.
 */
final class SubscriptionBannerComposer
{
    public function __construct(
        private readonly SubscriptionAccess $access,
        private readonly TenantContext $context,
    ) {}

    public function compose(View $view): void
    {
        $tenantId = $this->context->tenantId();

        if ($tenantId === null) {
            $view->with('subscriptionBanner', null);

            return;
        }

        $subscription = $this->access->currentFor($tenantId);
        $level = $this->access->levelFor($tenantId);

        $view->with('subscriptionBanner', match (true) {
            $subscription === null => null,
            $level === AccessLevel::ReadOnly => ['type' => 'read_only', 'days' => null],
            $level === AccessLevel::Blocked => ['type' => 'blocked', 'days' => null],
            $subscription->status === SubscriptionStatus::Trialing => ['type' => 'trial', 'days' => $subscription->daysLeft()],
            default => null,
        });
    }
}
