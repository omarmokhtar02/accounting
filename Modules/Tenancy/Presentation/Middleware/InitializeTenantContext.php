<?php

namespace Modules\Tenancy\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\TenantContext;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the authenticated user (never from request input) and the
 * active company from the session, accepting it only if the user is a member.
 */
class InitializeTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->tenant_id === null, 403);

        $tenant = $user->tenant;
        abort_if($tenant === null || $tenant->status !== 'active', 403);

        $this->context->setTenant($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $companies = $user->companies()->get();
        $activeId = (int) $request->session()->get('company_id');
        $company = $companies->firstWhere('id', $activeId) ?? $companies->first();

        // Always set (or clear) the company so a previous user's company can never linger in the context.
        $this->context->setCompany($company instanceof Company ? $company : null);

        if ($company instanceof Company) {
            $request->session()->put('company_id', $company->id);
        }

        return $next($request);
    }

    /**
     * Drop the tenant after the response so a reused (pooled) connection never carries it over.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->context->clear();
    }
}
