<?php

use Modules\Accounting\Providers\AccountingServiceProvider;

covers(AccountingServiceProvider::class);

it('registers the accounting views namespace', function () {
    expect(view()->exists('accounting::accounts.index'))->toBeTrue()
        ->and(view()->exists('accounting::journal-entries.show'))->toBeTrue()
        ->and(view()->exists('accounting::account-roles.index'))->toBeTrue();
});

it('registers every accounting route behind the tenant middleware', function () {
    $names = ['accounting.accounts.index', 'accounting.accounts.edit', 'accounting.accounts.status', 'accounting.account-roles.index',
        'accounting.fiscal-years.index', 'accounting.periods.close', 'accounting.journal-entries.index',
        'accounting.journal-entries.post', 'accounting.journal-entries.reverse'];

    foreach ($names as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()->and($route->gatherMiddleware())->toContain('auth', 'tenant', 'company');
    }
});

it('guards every state-changing route with a permission', function () {
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with((string) $route->getName(), 'accounting.')) {
            continue;
        }

        $guarded = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));

        expect($guarded)->toBeTrue("Route {$route->getName()} has no permission middleware");
    }
});
