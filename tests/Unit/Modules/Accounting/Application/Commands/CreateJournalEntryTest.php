<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\SaveExchangeRate;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Shared\Domain\Models\AuditLog;

covers(CreateJournalEntry::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    $this->cash = accountByRole(AccountRole::Cash);
    $this->sales = accountByRole(AccountRole::SalesRevenue);
});

function journalData(array $lines, ?string $key = null, ?string $sourceType = null): CreateJournalEntryData
{
    return new CreateJournalEntryData(CarbonImmutable::parse('2026-03-15'), 'Test', $lines, $key, $sourceType, $sourceType ? 1 : null);
}

it('creates a draft with its lines in the company currency', function () {
    $entry = draftJournal(25000);

    expect($entry->status)->toBe(JournalStatus::Draft)
        ->and($entry->currency)->toBe('EGP')
        ->and($entry->number)->toBeNull()
        ->and($entry->created_by)->toBe($this->user->id)
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->lines->sum('debit'))->toBe(25000)->and($entry->lines->sum('credit'))->toBe(25000);
});

it('audits the creation', function () {
    $entry = draftJournal();

    expect(AuditLog::where('entity_type', 'journal_entry')->where('entity_id', $entry->id)->pluck('action')->all())->toBe(['created']);
});

it('rejects unbalanced entries without writing anything', function () {
    expect(fn () => app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 1000, 0), new JournalLineData($this->sales->id, 0, 900),
    ])))->toThrow(JournalNotBalanced::class)
        ->and(JournalEntry::count())->toBe(0);
});

it('rejects inactive and non postable accounts', function () {
    $inactive = makeAccount($this->company, '9201');
    $inactive->update(['status' => AccountStatus::Inactive]);
    $header = $this->company->accounts()->where('code', '12')->first();

    foreach ([$inactive, $header] as $bad) {
        expect(fn () => app(CreateJournalEntry::class)->handle(journalData([
            new JournalLineData($bad->id, 100, 0), new JournalLineData($this->sales->id, 0, 100),
        ])))->toThrow(InvalidJournalState::class);
    }
});

it('rejects accounts that do not exist in the company', function () {
    app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData(999999, 100, 0), new JournalLineData($this->sales->id, 0, 100),
    ]));
})->throws(InvalidJournalState::class);

it('returns the existing entry for a repeated idempotency key', function () {
    $first = draftJournal(100, '2026-03-15', 'key-1');
    $second = draftJournal(100, '2026-03-15', 'key-1');

    expect($second->id)->toBe($first->id)->and(JournalEntry::count())->toBe(1);
});

it('stores the source reference of an operational document', function () {
    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 100, 0), new JournalLineData($this->sales->id, 0, 100),
    ], null, 'sales_invoice'));

    expect($entry->source_type)->toBe('sales_invoice')->and($entry->source_id)->toBe(1);
});

it('blocks manual postings to control accounts', function () {
    $receivable = accountByRole(AccountRole::AccountsReceivable);

    app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($receivable->id, 100, 0), new JournalLineData($this->sales->id, 0, 100),
    ]));
})->throws(AccountRoleError::class);

it('lets a sub-ledger document post to a control account', function () {
    $receivable = accountByRole(AccountRole::AccountsReceivable);

    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($receivable->id, 100, 0), new JournalLineData($this->sales->id, 0, 100),
    ], null, 'sales_invoice'));

    expect($entry->lines)->toHaveCount(2);
});

it('converts a foreign line at the table rate and keeps what was transacted', function () {
    app(SaveExchangeRate::class)->handle('USD', CarbonImmutable::parse('2026-03-01'), '48.5');

    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 0, 0, null, 'USD', 10000, null),
        new JournalLineData($this->sales->id, 0, 485000),
    ]));

    $foreign = $entry->lines->firstWhere('txn_currency', 'USD');

    expect($foreign->debit)->toBe(485000)->and($foreign->credit)->toBe(0)
        ->and($foreign->txn_debit)->toBe(10000)->and($foreign->txn_credit)->toBeNull()
        ->and((float) $foreign->exchange_rate)->toBe(48.5)
        ->and($entry->lines->sum('debit'))->toBe($entry->lines->sum('credit'));
});

it('uses a rate typed on the line instead of the table', function () {
    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 0, 0, null, 'USD', 1000, null, '50'),
        new JournalLineData($this->sales->id, 0, 50000),
    ]));

    expect($entry->lines->firstWhere('txn_currency', 'USD')->debit)->toBe(50000);
});

it('handles a foreign credit line', function () {
    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 50000, 0),
        new JournalLineData($this->sales->id, 0, 0, null, 'USD', null, 1000, '50'),
    ]));

    expect($entry->lines->firstWhere('txn_currency', 'USD')->credit)->toBe(50000);
});

it('treats a line in the base currency as an ordinary line', function () {
    $entry = app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 1000, 0, null, 'EGP'),
        new JournalLineData($this->sales->id, 0, 1000),
    ]));

    expect($entry->lines->whereNotNull('txn_currency'))->toHaveCount(0);
});

it('refuses a foreign line without a rate in the table', function () {
    app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 0, 0, null, 'USD', 1000, null),
        new JournalLineData($this->sales->id, 0, 50000),
    ]));
})->throws(ExchangeRateError::class);

it('refuses a foreign line with both or neither side', function (?int $debit, ?int $credit) {
    app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 0, 0, null, 'USD', $debit, $credit, '50'),
        new JournalLineData($this->sales->id, 0, 50000),
    ]));
})->with([[1000, 1000], [null, null], [0, 0]])->throws(ExchangeRateError::class);

it('still rejects an entry that is unbalanced in the base currency', function () {
    app(CreateJournalEntry::class)->handle(journalData([
        new JournalLineData($this->cash->id, 0, 0, null, 'USD', 1000, null, '50'),
        new JournalLineData($this->sales->id, 0, 49999),
    ]));
})->throws(JournalNotBalanced::class);
