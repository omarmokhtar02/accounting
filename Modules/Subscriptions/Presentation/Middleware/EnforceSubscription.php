<?php

namespace Modules\Subscriptions\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Enums\AccessLevel;
use Modules\Tenancy\Infrastructure\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the tenant's subscription to every business route: full access, read-only during the grace
 * period (reads allowed, writes redirected to billing), or blocked (billing page only).
 */
class EnforceSubscription
{
    public function __construct(
        private readonly SubscriptionAccess $access,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $level = $this->access->levelFor((int) $this->context->tenantId());

        if ($level === AccessLevel::Full) {
            return $next($request);
        }

        if ($level === AccessLevel::ReadOnly && $request->isMethodSafe()) {
            return $next($request);
        }

        return redirect()->route('billing.show')->with('billing_notice', $level === AccessLevel::ReadOnly ? 'read_only' : 'blocked');
    }
}
