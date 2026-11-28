<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\ReverseJournalEntry;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Shared\Domain\Models\AuditLog;

covers(ReverseJournalEntry::class);

beforeEach(function () {
    ['user' => $this->user] = bootstrapTenant();
    openFiscalYear2026();
    $this->original = postedJournal($this->user, 12345);
});

it('creates a posted, linked, opposite entry and keeps the original lines', function () {
    $reversal = app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-03-20'), 'wrong customer');
    $this->original->refresh();

    expect($this->original->status)->toBe(JournalStatus::Reversed)
        ->and($this->original->reversed_by_id)->toBe($reversal->id)
        ->and($reversal->reverses_id)->toBe($this->original->id)
        ->and($reversal->status)->toBe(JournalStatus::Posted)
        ->and($reversal->number)->toBe('JV-2026-000002')
        ->and($reversal->currency)->toBe($this->original->currency)
        ->and((int) $this->original->lines()->sum('debit'))->toBe(12345);

    foreach ($this->original->lines as $line) {
        $mirror = $reversal->lines()->where('account_id', $line->account_id)->first();
        expect([$mirror->debit, $mirror->credit])->toBe([$line->credit, $line->debit]);
    }
});

it('nets the ledger to zero after a reversal', function () {
    app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-03-20'));

    expect((int) JournalLine::sum('debit'))->toBe((int) JournalLine::sum('credit'))->toBe(24690);
});

it('records the reason in the audit trail', function () {
    app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-03-20'), 'duplicate');

    $log = AuditLog::where('entity_id', $this->original->id)->where('action', 'reversed')->first();

    expect($log->reason)->toBe('duplicate')->and($log->old_values)->toBe(['status' => 'posted']);
});

it('cannot reverse a draft or an already reversed entry', function () {
    expect(fn () => app(ReverseJournalEntry::class)->handle(draftJournal(), $this->user, CarbonImmutable::parse('2026-03-20')))
        ->toThrow(InvalidJournalState::class);

    app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-03-20'));

    expect(fn () => app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-03-21')))
        ->toThrow(InvalidJournalState::class);
});

it('cannot reverse into a closed period or outside the fiscal year', function () {
    AccountingPeriod::whereDate('starts_on', '2026-04-01')->update(['status' => 'closed']);

    expect(fn () => app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2026-04-10')))
        ->toThrow(AccountingPeriodClosed::class)
        ->and(fn () => app(ReverseJournalEntry::class)->handle($this->original, $this->user, CarbonImmutable::parse('2030-01-10')))
        ->toThrow(AccountingPeriodClosed::class);

    expect($this->original->fresh()->status)->toBe(JournalStatus::Posted);
});
