<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Modules\Identity\Presentation\Middleware\SetLocale;
use Modules\Platform\Presentation\Middleware\EnsureAdminIsActive;
use Modules\Platform\Presentation\Middleware\RequireAdminPermission;
use Modules\Subscriptions\Presentation\Middleware\EnforceSubscription;
use Modules\Tenancy\Presentation\Middleware\InitializeTenantContext;
use Modules\Tenancy\Presentation\Middleware\RequireCompany;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a load balancer / nginx: TRUSTED_PROXIES="*" or a comma separated list of IPs.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);

        // Route-model binding resolves tenant-scoped models, so the tenant context must already be
        // initialised when SubstituteBindings runs (after authentication, before binding).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: InitializeTenantContext::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequireCompany::class);

        // Platform admins have their own login; send guests and logged-in admins to the right place.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform*') ? route('platform.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('platform*') ? route('platform.dashboard') : route('dashboard'));

        $middleware->alias([
            'tenant' => InitializeTenantContext::class,
            'company' => RequireCompany::class,
            'subscribed' => EnforceSubscription::class,
            'platform.can' => RequireAdminPermission::class,
            'platform.active' => EnsureAdminIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
