<?php

namespace Modules\Tenancy\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Infrastructure\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireCompany
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->context->company() === null, 403);

        return $next($request);
    }
}
