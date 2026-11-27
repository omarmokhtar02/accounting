<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Platform\Domain\Models\AdminUser;
use Modules\Platform\Presentation\Controllers\AuthController;
use Modules\Platform\Presentation\Controllers\PlatformLocaleController;
use Modules\Platform\Presentation\Middleware\EnsureAdminIsActive;
use Modules\Platform\Presentation\Middleware\RequireAdminPermission;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Tenancy\Domain\Models\Tenant;

covers(AuthController::class, PlatformLocaleController::class, EnsureAdminIsActive::class, RequireAdminPermission::class, PlatformServiceProvider::class);

describe('admin sign in', function () {
    it('shows the login page without a session', function () {
        $this->get(route('platform.login'))->assertOk()->assertSee('Admin sign in')->assertSee('noindex', false);
    });

    it('signs an admin in, records the time and lands on the dashboard', function () {
        $admin = makeAdmin('super_admin', ['email' => 'boss@example.com', 'password' => 'a-long-enough-pass']);

        $this->post(route('platform.login.store'), ['email' => 'boss@example.com', 'password' => 'a-long-enough-pass'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($admin->fresh(), 'admin');
        expect($admin->fresh()->last_login_at)->not->toBeNull();
    });

    it('rejects a wrong password with a generic message', function () {
        makeAdmin('super_admin', ['email' => 'boss@example.com', 'password' => 'a-long-enough-pass']);

        $this->post(route('platform.login.store'), ['email' => 'boss@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    });

    it('rejects deactivated admins', function () {
        makeAdmin('viewer', ['email' => 'off@example.com', 'password' => 'a-long-enough-pass', 'is_active' => false]);

        $this->post(route('platform.login.store'), ['email' => 'off@example.com', 'password' => 'a-long-enough-pass'])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    });

    it('validates the form', function () {
        $this->post(route('platform.login.store'), [])->assertSessionHasErrors(['email', 'password']);
    });

    it('locks out after five failed attempts and tells the admin how long to wait', function () {
        makeAdmin('super_admin', ['email' => 'boss@example.com', 'password' => 'a-long-enough-pass']);
        RateLimiter::clear('platform-login:boss@example.com|127.0.0.1');

        foreach (range(1, 5) as $_) {
            $this->post(route('platform.login.store'), ['email' => 'boss@example.com', 'password' => 'nope']);
        }

        // Even the right password is refused while locked out.
        $this->post(route('platform.login.store'), ['email' => 'boss@example.com', 'password' => 'a-long-enough-pass'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        RateLimiter::clear('platform-login:boss@example.com|127.0.0.1');
    });

    it('signs out and ends the session', function () {
        actingAsAdmin();

        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));

        $this->assertGuest('admin');
    });
});

describe('separation from tenant users', function () {
    it('sends guests to the platform login, not the customer login', function () {
        $this->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->get(route('platform.tenants.index'))->assertRedirect(route('platform.login'));
    });

    it('sends logged-in admins away from the login page', function () {
        actingAsAdmin();

        $this->get(route('platform.login'))->assertRedirect(route('platform.dashboard'));
    });

    it('does not let a customer user into the platform', function () {
        ['user' => $user] = bootstrapTenant();

        $this->actingAs($user)->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
    });

    it('does not let an admin into the customer app', function () {
        actingAsAdmin();
        // actingAs(..., 'admin') makes 'admin' the default guard; a real customer request uses 'web'.
        Auth::shouldUse('web');

        $this->get(route('accounting.accounts.index'))->assertRedirect(route('login'));
    });
});

describe('EnsureAdminIsActive', function () {
    it('logs out an admin who was deactivated while signed in', function () {
        $admin = actingAsAdmin('viewer');
        AdminUser::whereKey($admin->id)->update(['is_active' => false]);

        $this->actingAs($admin->fresh(), 'admin')->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));

        $this->assertGuest('admin');
    });

    it('lets active admins through', function () {
        actingAsAdmin('viewer');

        $this->get(route('platform.dashboard'))->assertOk();
    });
});

describe('RequireAdminPermission', function () {
    it('allows or forbids per permission', function (string $role, string $method, string $route, array $parameters, int $status) {
        actingAsAdmin($role);
        $tenant = Tenant::factory()->create();
        $parameters = array_map(fn ($p) => $p === 'TENANT' ? $tenant : $p, $parameters);

        $response = $this->{$method}(route($route, $parameters));

        expect($response->getStatusCode())->toBe($status);
    })->with([
        'viewer views dashboard' => ['viewer', 'get', 'platform.dashboard', [], 200],
        'viewer views tenants' => ['viewer', 'get', 'platform.tenants.index', [], 200],
        'viewer cannot suspend' => ['viewer', 'post', 'platform.tenants.suspend', ['TENANT'], 403],
        'viewer cannot manage plans' => ['viewer', 'get', 'platform.plans.index', [], 403],
        'viewer cannot manage settings' => ['viewer', 'get', 'platform.settings.edit', [], 403],
        'viewer cannot manage admins' => ['viewer', 'get', 'platform.admins.index', [], 403],
        'viewer cannot manage roles' => ['viewer', 'get', 'platform.roles.index', [], 403],
        'viewer views audit' => ['viewer', 'get', 'platform.audit.index', [], 200],
        'support can suspend' => ['support', 'post', 'platform.tenants.suspend', ['TENANT'], 302],
        'support cannot manage plans' => ['support', 'get', 'platform.plans.index', [], 403],
        'support cannot view audit' => ['support', 'get', 'platform.audit.index', [], 403],
        'finance manages plans' => ['finance', 'get', 'platform.plans.index', [], 200],
        'finance cannot suspend' => ['finance', 'post', 'platform.tenants.suspend', ['TENANT'], 403],
        'finance cannot manage admins' => ['finance', 'get', 'platform.admins.index', [], 403],
        'super admin manages roles' => ['super_admin', 'get', 'platform.roles.index', [], 200],
        'super admin manages settings' => ['super_admin', 'get', 'platform.settings.edit', [], 200],
    ]);

    it('protects every platform route behind admin auth and a permission', function () {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'platform.') || in_array($name, ['platform.login', 'platform.login.store', 'platform.locale', 'platform.logout'], true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            expect($middleware)->toContain('auth:admin');

            if ($name !== 'platform.logout') {
                expect(collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'platform.can:')))->toBeTrue("{$name} has no permission middleware");
            }
        }
    });
});

describe('platform language', function () {
    it('switches the language for guests and remembers it for admins', function () {
        $this->get(route('platform.locale', 'ar'))->assertRedirect();
        expect(session('locale'))->toBe('ar');

        $admin = actingAsAdmin('viewer');
        $this->get(route('platform.locale', 'ar'))->assertRedirect();

        expect($admin->fresh()->locale)->toBe('ar');
    });

    it('rejects unsupported languages and renders the admin UI right-to-left in Arabic', function () {
        $this->get('/platform/locale/fr')->assertNotFound();

        actingAsAdmin('viewer');
        $this->withSession(['locale' => 'ar'])->get(route('platform.dashboard'))->assertSee('dir="rtl"', false)->assertSee('لوحة المعلومات');
    });
});

it('registers the platform views, components and @adminCan directive', function () {
    expect(view()->exists('platform::dashboard'))->toBeTrue()
        ->and(view()->exists('platform::components.layout'))->toBeTrue();

    $admin = makeAdmin('viewer');
    $this->actingAs($admin, 'admin');

    $html = Blade::render("@adminCan('tenants.view') yes @endadminCan |@adminCan('plans.manage') no @endadminCan");

    expect(trim(preg_replace('/\s+/', ' ', $html)))->toBe('yes |');
});
