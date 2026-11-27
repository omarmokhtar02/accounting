<?php

namespace Modules\Subscriptions\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;
use Modules\Shared\Application\PlatformSettings;
use Modules\Subscriptions\Application\SubscriptionAccess;
use Modules\Subscriptions\Domain\Models\Plan;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;

class BillingController extends Controller
{
    public function show(SubscriptionAccess $access, TenantContext $context, PlatformSettings $settings): View
    {
        $tenantId = (int) $context->tenantId();
        $subscriptions = $access->allFor($tenantId);

        return view('subscriptions::billing.show', [
            'subscriptions' => $subscriptions,
            'limits' => $access->limitsFor($tenantId),
            'level' => $access->levelFor($tenantId),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'usage' => [
                'users' => User::query()->where('tenant_id', $tenantId)->count(),
                'companies' => Company::query()->count(),
            ],
            'instructions' => app()->getLocale() === 'ar' ? $settings->string('billing.instructions_ar') : $settings->string('billing.instructions_en'),
            'contactEmail' => $settings->string('billing.contact_email'),
        ]);
    }
}
