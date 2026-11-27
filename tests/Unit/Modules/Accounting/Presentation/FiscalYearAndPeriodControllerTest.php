<?php

use Illuminate\Database\QueryException;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\FiscalYear;
use Modules\Accounting\Presentation\Controllers\FiscalYearController;
use Modules\Accounting\Presentation\Controllers\PeriodController;
use Modules\Accounting\Presentation\Requests\StoreFiscalYearRequest;
use Modules\Tenancy\Domain\Models\Company;

covers(FiscalYearController::class, PeriodController::class, StoreFiscalYearRequest::class);

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
});

it('lists fiscal years with their periods', function () {
    openFiscalYear2026();

    $this->get(route('accounting.fiscal-years.index'))->assertOk()->assertSee('2026-01-31')->assertSee('Close period');
});

it('creates a fiscal year with monthly periods', function () {
    $this->get(route('accounting.fiscal-years.create'))->assertOk();
    $this->post(route('accounting.fiscal-years.store'), ['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])
        ->assertRedirect(route('accounting.fiscal-years.index'));

    restoreContext($this->owner);
    expect(FiscalYear::count())->toBe(1)->and(AccountingPeriod::count())->toBe(12);
});

it('validates fiscal years', function (array $input, string $field) {
    $this->post(route('accounting.fiscal-years.store'), $input + ['name' => 'Y', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])
        ->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad start' => [['starts_on' => 'tomorrow-ish'], 'starts_on'],
    'end before start' => [['ends_on' => '2026-12-31'], 'ends_on'],
]);

it('closes a period and shows an error while drafts remain', function () {
    openFiscalYear2026();
    $march = AccountingPeriod::whereDate('starts_on', '2026-03-01')->first();
    $draft = draftJournal(100, '2026-03-20');

    $this->post(route('accounting.periods.close', $march))->assertSessionHasErrors('period');

    restoreContext($this->owner);
    $draft->delete();
    $this->post(route('accounting.periods.close', $march))->assertSessionHas('status');
    restoreContext($this->owner);
    expect($march->fresh()->isOpen())->toBeFalse();
});

it('returns 404 when closing a period of another company', function () {
    openFiscalYear2026();
    $other = Company::create(['name' => 'B', 'country_code' => 'EG', 'base_currency' => 'EGP']);
    $foreign = AccountingPeriod::create([
        'company_id' => $other->id, 'fiscal_year_id' => FiscalYear::first()->id, 'number' => 99,
        'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31',
    ]);
})->throws(QueryException::class);

it('enforces permissions', function () {
    openFiscalYear2026();
    $period = AccountingPeriod::first();
    actingAsRole('accountant');

    $this->get(route('accounting.fiscal-years.index'))->assertOk();
    $this->get(route('accounting.fiscal-years.create'))->assertForbidden();
    $this->post(route('accounting.periods.close', $period))->assertForbidden();
});
