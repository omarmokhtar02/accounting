<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Tenancy\Infrastructure\TenantContext;

class DashboardController extends Controller
{
    public function __invoke(SubscriptionAccess $access, TenantContext $context): View
    {
        $tenantId = (int) $context->tenantId();

        return view('dashboard', [
            'company' => $context->company(),
            'subscriptions' => $access->allFor($tenantId),
            'level' => $access->levelFor($tenantId),
        ]);
    }
}
