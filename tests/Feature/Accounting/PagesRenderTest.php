<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\OpenFiscalYear;

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    makeAccount($this->company, '1000');
});

it('renders every accounting and admin page for the owner in both locales', function (string $locale) {
    $this->withSession(['locale' => $locale]);

    foreach ([
        'dashboard', 'accounting.accounts.index', 'accounting.accounts.create',
        'accounting.fiscal-years.index', 'accounting.fiscal-years.create',
        'accounting.journal-entries.index', 'accounting.journal-entries.create',
        'admin.users.index', 'admin.users.create', 'admin.roles.index',
    ] as $route) {
        $this->get(route($route))->assertOk();
    }
})->with(['en', 'ar']);

it('renders right-to-left markup for Arabic', function () {
    $this->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertSee('dir="rtl"', false);
});
