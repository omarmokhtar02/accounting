<?php

use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Accounting\Presentation\Controllers\ExchangeRateController;
use Modules\Accounting\Presentation\Requests\StoreExchangeRateRequest;

covers(ExchangeRateController::class, StoreExchangeRateRequest::class);

beforeEach(function () {
    $this->owner = bootstrapTenant()['user'];
});

it('lists the saved rates and offers every currency except the base', function () {
    $this->post(route('accounting.exchange-rates.store'), ['currency_code' => 'USD', 'rate_date' => '2026-03-01', 'rate' => '48.5']);
    restoreContext($this->owner);

    $this->get(route('accounting.exchange-rates.index'))->assertOk()->assertSee('48.5')->assertSee('USD')
        ->assertSee('<option value="SAR"', false)->assertDontSee('<option value="EGP"', false);
});

it('renders in Arabic', function () {
    $this->withSession(['locale' => 'ar'])->get(route('accounting.exchange-rates.index'))->assertOk()->assertSee('أسعار الصرف');
});

it('saves a rate and replaces the same day', function () {
    $this->post(route('accounting.exchange-rates.store'), ['currency_code' => 'USD', 'rate_date' => '2026-03-01', 'rate' => '48.5'])
        ->assertRedirect(route('accounting.exchange-rates.index'));
    restoreContext($this->owner);
    $this->post(route('accounting.exchange-rates.store'), ['currency_code' => 'USD', 'rate_date' => '2026-03-01', 'rate' => '49']);
    restoreContext($this->owner);

    expect(ExchangeRateEntry::count())->toBe(1)->and((float) ExchangeRateEntry::first()->rate)->toBe(49.0);
});

it('rejects bad input', function (array $input, string $field) {
    $this->post(route('accounting.exchange-rates.store'), $input + ['currency_code' => 'USD', 'rate_date' => '2026-03-01', 'rate' => '48'])
        ->assertSessionHasErrors($field);
})->with([
    'unknown currency' => [['currency_code' => 'ZZZ'], 'currency_code'],
    'base currency' => [['currency_code' => 'EGP'], 'currency_code'],
    'zero rate' => [['rate' => '0'], 'rate'],
    'text rate' => [['rate' => 'abc'], 'rate'],
    'bad date' => [['rate_date' => '01/03/2026'], 'rate_date'],
]);

it('lets viewers look but not change', function () {
    restoreContext($this->owner);
    actingAsRole('viewer');

    $this->get(route('accounting.exchange-rates.index'))->assertOk()->assertDontSee('name="rate"', false);
    $this->post(route('accounting.exchange-rates.store'), ['currency_code' => 'USD', 'rate_date' => '2026-03-01', 'rate' => '48'])->assertForbidden();
});
