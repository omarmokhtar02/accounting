<?php

namespace Modules\Platform\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An admin deactivated while logged in loses access on their next request.
 */
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin !== null && ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();

            return redirect()->route('platform.login')->withErrors(['email' => __('platform.errors.deactivated')]);
        }

        return $next($request);
    }
}
