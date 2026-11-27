<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Accounting\Application\Commands\ReverseJournalEntry;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\DocumentSequence;
use Modules\Accounting\Domain\Models\FiscalYear;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Domain\Models\JournalLine;

covers(AccountingPeriod::class, DocumentSequence::class, FiscalYear::class, JournalEntry::class, JournalLine::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    openFiscalYear2026();
});

it('orders a fiscal year\'s periods by number and casts dates', function () {
    $year = FiscalYear::first();

    expect($year->starts_on)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($year->periods->pluck('number')->all())->toBe(range(1, 12))
        ->and($year->company->id)->toBe($this->company->id);
});

it('knows whether a period is open', function () {
    $period = AccountingPeriod::first();

    expect($period->isOpen())->toBeTrue()
        ->and($period->fiscalYear->name)->toBe('2026');

    $period->update(['status' => AccountingPeriod::STATUS_CLOSED]);

    expect($period->fresh()->isOpen())->toBeFalse();
});

it('relates a journal entry to its lines, period, creator and reversal chain', function () {
    $entry = postedJournal($this->user);

    expect($entry->status)->toBe(JournalStatus::Posted)
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->period->id)->toBe($entry->accounting_period_id)
        ->and($entry->creator->id)->toBe($this->user->id)
        ->and($entry->company->id)->toBe($this->company->id)
        ->and($entry->reverses)->toBeNull()
        ->and($entry->reversedBy)->toBeNull();

    $line = $entry->lines->first();
    expect($line->entry->id)->toBe($entry->id)->and($line->account->id)->toBe($line->account_id)
        ->and($line->debit)->toBeInt()->and($line->credit)->toBeInt();
});

it('stores a document sequence per company, type and year', function () {
    DocumentSequence::create(['company_id' => $this->company->id, 'type' => 'journal', 'year' => 2026, 'last_number' => 4]);

    expect(DocumentSequence::first()->last_number)->toBe(4);
});

it('enforces unique numbers per company', function () {
    $a = postedJournal($this->user);
    $b = draftJournal();

    $b->update(['number' => $a->number]);
})->throws(UniqueConstraintViolationException::class);

it('only allows a posted entry to be reversed once', function () {
    $entry = postedJournal($this->user);
    $reversal = app(ReverseJournalEntry::class)->handle($entry, $this->user, CarbonImmutable::parse('2026-03-20'));

    expect($entry->fresh()->reversedBy->id)->toBe($reversal->id)->and($reversal->reverses->id)->toBe($entry->id);

    JournalEntry::create([
        'company_id' => $this->company->id, 'entry_date' => '2026-03-21', 'description' => 'dup', 'status' => 'posted',
        'currency' => 'EGP', 'reverses_id' => $entry->id, 'created_by' => $this->user->id,
    ]);
})->throws(UniqueConstraintViolationException::class);
