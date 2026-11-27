<?php

namespace Modules\Platform\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: platform.can:tenants.manage — 403 unless the logged-in admin has the permission.
 */
class RequireAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user('admin')?->hasPermission($permission), 403);

        return $next($request);
    }
}
