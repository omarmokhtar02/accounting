<?php

use Carbon\CarbonImmutable;
use Modules\Localization\Application\SyncTaxDefaults;
use Modules\Platform\Application\SaveAdminRole;
use Modules\Platform\Application\SaveTaxRate;
use Modules\Platform\Application\TaxRateOverview;
use Modules\Platform\Domain\Models\PlatformAuditLog;
use Modules\Platform\Presentation\Controllers\TaxRateController;
use Modules\Tax\Application\TaxRateResolver;
use Modules\Tax\Domain\Exceptions\InvalidTaxRateChange;
use Modules\Tax\Domain\Models\TaxRateSchedule;
use Modules\Tax\Presentation\Controllers\TaxCodeController;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

covers(SaveTaxRate::class, TaxRateOverview::class, TaxRateController::class, TaxCodeController::class);

beforeEach(function () {
    app(SyncTaxDefaults::class)->handle();
});

describe('SaveTaxRate', function () {
    it('schedules a rate for a country code, converting percent to basis points, and audits it', function () {
        $row = app(SaveTaxRate::class)->schedule('EG', 'WHT', '2.5', now(), 'Decree 5', $adminId = makeAdmin()->id);

        expect($row->rate_bp)->toBe(250)->and($row->kind->value)->toBe('withholding')->and($row->created_by)->toBe($adminId)
            ->and(PlatformAuditLog::where('action', 'tax_rate_scheduled')->first()->data)->toMatchArray(['country' => 'EG', 'code' => 'WHT', 'rate_bp' => 250]);
    });

    it('accepts the percent formats an accountant would type', function (string $percent, int $bp) {
        expect(app(SaveTaxRate::class)->schedule('EG', 'TABLE_TAX', $percent, now())->rate_bp)->toBe($bp);
    })->with([['14', 1400], ['14.5', 1450], ['0.05', 5], ['0', 0], ['100', 10000], ['7.25', 725]]);

    it('rejects malformed or out-of-range percentages', function (string $percent) {
        app(SaveTaxRate::class)->schedule('EG', 'WHT', $percent, now());
    })->with(['abc', '', '14.123', '-1', '101', '1000', '1,5'])->throws(InvalidTaxRateChange::class);

    it('rejects unknown countries and unknown codes', function () {
        expect(fn () => app(SaveTaxRate::class)->schedule('ZZ', 'VAT_STD', '14', now()))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => app(SaveTaxRate::class)->schedule('EG', 'NOPE', '14', now()))->toThrow(InvalidTaxRateChange::class)
            ->and(fn () => app(SaveTaxRate::class)->schedule('KW', 'VAT_STD', '5', now()))->toThrow(InvalidTaxRateChange::class);
    });

    it('removes a future rate and audits it; refuses one in force', function () {
        $future = app(SaveTaxRate::class)->schedule('EG', 'VAT_STD', '15', now()->addDays(20));

        app(SaveTaxRate::class)->remove($future);

        expect(TaxRateSchedule::find($future->id))->toBeNull()->and(PlatformAuditLog::where('action', 'tax_rate_removed')->count())->toBe(1);

        $inForce = TaxRateSchedule::where('country_code', 'EG')->where('code', 'VAT_STD')->first();

        expect(fn () => app(SaveTaxRate::class)->remove($inForce))->toThrow(InvalidTaxRateChange::class);
    });
});

describe('TaxRateOverview', function () {
    it('lists every installed country with each tax code, current rate, next change and history', function () {
        app(SaveTaxRate::class)->schedule('EG', 'VAT_STD', '15', now()->addDays(30));

        $overview = app(TaxRateOverview::class)->build();

        expect(array_keys($overview))->toBe(['EG', 'SA', 'KW'])
            ->and($overview['KW']['codes'])->toBe([])
            ->and(collect($overview['EG']['codes'])->pluck('code')->all())->toBe(['VAT_STD', 'VAT_ZERO', 'VAT_EXEMPT', 'WHT', 'TABLE_TAX', 'STAMP_DUTY']);

        $vat = collect($overview['EG']['codes'])->firstWhere('code', 'VAT_STD');
        $wht = collect($overview['EG']['codes'])->firstWhere('code', 'WHT');

        expect($vat['current']->basisPoints)->toBe(1400)->and($vat['next']->rate_bp)->toBe(1500)->and($vat['history'])->toHaveCount(2)
            ->and($wht['current'])->toBeNull()
            ->and(collect($overview['SA']['codes'])->firstWhere('code', 'VAT_STD')['current']->basisPoints)->toBe(1500);
    });

    it('localises country and code names', function () {
        app()->setLocale('ar');
        $overview = app(TaxRateOverview::class)->build();

        expect($overview['EG']['name'])->toBe('مصر')->and($overview['EG']['codes'][0]['name'])->toContain('ضريبة القيمة المضافة');
    });
});

describe('admin tax rates screen', function () {
    it('shows each country, its taxes, rates, next changes and the "not configured" warning', function () {
        actingAsAdmin();

        $this->get(route('platform.tax-rates.index'))->assertOk()
            ->assertSee('Egypt (EG)')->assertSee('Saudi Arabia (SA)')->assertSee('Kuwait (KW)')
            ->assertSee('VAT_STD')->assertSee('14%')->assertSee('15%')->assertSee('Not configured yet')->assertSee('No taxes are defined for this country.');
    });

    it('is Arabic in the Arabic UI', function () {
        actingAsAdmin();

        $this->withSession(['locale' => 'ar'])->get(route('platform.tax-rates.index'))->assertOk()->assertSee('نسب الضرائب حسب الدولة')->assertSee('مصر');
    });

    it('sets a new rate from the form and the system uses it', function () {
        actingAsAdmin();

        $this->post(route('platform.tax-rates.store'), ['country' => 'EG', 'code' => 'VAT_STD', 'percent' => '15', 'effective_from' => now()->toDateString(), 'note' => 'law 9'])
            ->assertSessionHas('status');

        expect(app(TaxRateResolver::class)->rateFor('EG', 'VAT_STD', now())->basisPoints)->toBe(1500)
            ->and(app(TaxRateResolver::class)->rateFor('EG', 'VAT_STD', CarbonImmutable::parse('2020-01-01'))->basisPoints)->toBe(1400);
    });

    it('validates the form and reports business errors', function (array $input, string $field) {
        actingAsAdmin();

        $this->post(route('platform.tax-rates.store'), $input + ['country' => 'EG', 'code' => 'VAT_STD', 'percent' => '15', 'effective_from' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors($field);
    })->with([
        'unknown country' => [['country' => 'ZZ'], 'country'],
        'bad percent' => [['percent' => '14.999'], 'percent'],
        'bad date' => [['effective_from' => 'soon'], 'effective_from'],
        'past date' => [['effective_from' => '2000-01-01'], 'rate'],
        'unknown code' => [['code' => 'NOPE'], 'rate'],
    ]);

    it('removes only scheduled future rates', function () {
        actingAsAdmin();
        $future = app(SaveTaxRate::class)->schedule('EG', 'VAT_STD', '16', now()->addDays(10));
        $inForce = TaxRateSchedule::where('country_code', 'EG')->where('code', 'VAT_STD')->first();

        $this->delete(route('platform.tax-rates.destroy', $future))->assertSessionHas('status');
        $this->delete(route('platform.tax-rates.destroy', $inForce))->assertSessionHasErrors('rate');

        expect(TaxRateSchedule::find($future->id))->toBeNull()->and(TaxRateSchedule::find($inForce->id))->not->toBeNull();
    });

    it('requires the tax.manage permission', function (string $role, int $status) {
        actingAsAdmin($role);

        expect($this->get(route('platform.tax-rates.index'))->getStatusCode())->toBe($status);
    })->with([['super_admin', 200], ['support', 403], ['finance', 403], ['viewer', 403]]);

    it('lets a custom role manage tax rates', function () {
        $role = app(SaveAdminRole::class)->create('Tax team', 'فريق الضرائب', ['tax.manage', 'dashboard.view']);
        $this->actingAs(makeAdmin('viewer', ['admin_role_id' => $role->id]), 'admin');

        $this->get(route('platform.tax-rates.index'))->assertOk()->assertSee('Tax rates');
        $this->post(route('platform.tax-rates.store'), ['country' => 'SA', 'code' => 'WHT_NONRESIDENT', 'percent' => '5', 'effective_from' => now()->toDateString()])->assertSessionHas('status');
    });
});

describe('tenant tax page', function () {
    it('shows the company\'s codes with today\'s rate and the next scheduled change', function () {
        ['user' => $user] = bootstrapTenant();
        app(SaveTaxRate::class)->schedule('EG', 'VAT_STD', '15', now()->addDays(30));

        $this->get(route('tax.codes.index'))->assertOk()->assertSee('VAT_STD')->assertSee('14%')->assertSee('15%')->assertSee('Not configured yet');
    });

    it('follows a rate change made by the platform without any action by the company', function () {
        bootstrapTenant();
        $this->get(route('tax.codes.index'))->assertSee('14%');

        app(SaveTaxRate::class)->schedule('EG', 'VAT_STD', '16', now());
        $this->get(route('tax.codes.index'))->assertSee('16%')->assertDontSee('14%');
    });

    it('shows the Saudi rate for a Saudi company and nothing for a Kuwaiti one', function () {
        bootstrapTenant('sa@example.com', 'Riyadh Co', 'SA');
        $this->get(route('tax.codes.index'))->assertSee('15%')->assertDontSee('Table tax');

        bootstrapTenant('kw@example.com', 'Kuwait Co', 'KW');
        $this->get(route('tax.codes.index'))->assertOk()->assertSee('No taxes are defined for this country.');
    });

    it('requires login and the tax.view permission, and is gated by the subscription', function () {
        $this->get(route('tax.codes.index'))->assertRedirect(route('login'));

        ['user' => $owner] = bootstrapTenant();
        actingAsRole('senior_accountant');
        $this->get(route('tax.codes.index'))->assertOk();
        restoreContext($owner);

        $role = Role::where('name', 'viewer')->first();
        $role->revokePermissionTo('tax.view');
        actingAsRole('viewer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('tax.codes.index'))->assertForbidden();
    });
});
