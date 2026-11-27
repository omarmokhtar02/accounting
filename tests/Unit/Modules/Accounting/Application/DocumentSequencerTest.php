<?php

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\DocumentSequencer;
use Modules\Accounting\Domain\Models\DocumentSequence;
use Modules\Tenancy\Domain\Models\Company;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(DocumentSequencer::class);

beforeEach(function () {
    ['company' => $this->company] = bootstrapTenant();
    $this->sequencer = app(DocumentSequencer::class);
});

it('numbers sequentially with a zero padded counter', function () {
    expect($this->sequencer->next($this->company->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000001')
        ->and($this->sequencer->next($this->company->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000002')
        ->and($this->sequencer->next($this->company->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000003');
});

it('keeps separate counters per type and per year', function () {
    $this->sequencer->next($this->company->id, 'journal', 2026, 'JV');

    expect($this->sequencer->next($this->company->id, 'journal', 2027, 'JV'))->toBe('JV-2027-000001')
        ->and($this->sequencer->next($this->company->id, 'invoice', 2026, 'INV'))->toBe('INV-2026-000001')
        ->and($this->sequencer->next($this->company->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000002');
});

it('keeps separate counters per company', function () {
    $second = Company::create(['name' => 'Second', 'country_code' => 'EG', 'base_currency' => 'EGP']);

    $this->sequencer->next($this->company->id, 'journal', 2026, 'JV');

    expect($this->sequencer->next($second->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000001');
});

it('stores one row per company, type and year', function () {
    foreach (range(1, 3) as $_) {
        $this->sequencer->next($this->company->id, 'journal', 2026, 'JV');
    }

    expect(DocumentSequence::count())->toBe(1)->and(DocumentSequence::first()->last_number)->toBe(3);
});

it('does not burn a number when the surrounding transaction rolls back', function () {
    try {
        DB::transaction(function () {
            $this->sequencer->next($this->company->id, 'journal', 2026, 'JV');
            throw new RuntimeException('post failed');
        });
    } catch (RuntimeException) {
    }

    expect($this->sequencer->next($this->company->id, 'journal', 2026, 'JV'))->toBe('JV-2026-000001');
});

it('needs a tenant context', function () {
    app(TenantContext::class)->clear();

    $this->sequencer->next($this->company->id, 'journal', 2026, 'JV');
})->throws(InvalidTenantContext::class);
