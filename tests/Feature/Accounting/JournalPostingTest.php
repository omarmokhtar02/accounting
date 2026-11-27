<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\OpenFiscalYear;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\Commands\ReverseJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Shared\Domain\Models\AuditLog;

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    app(OpenFiscalYear::class)->handle('2026', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    $this->cash = makeAccount($this->company, '1000');
    $this->sales = makeAccount($this->company, '4000', AccountType::Revenue);
});

function draftEntry(object $test, int $debit = 10000, int $credit = 10000, string $date = '2026-03-15', ?string $key = null): JournalEntry
{
    return app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse($date),
        'Test sale',
        [
            new JournalLineData($test->cash->id, $debit, 0),
            new JournalLineData($test->sales->id, 0, $credit),
        ],
        $key,
    ));
}

it('creates twelve monthly periods for a fiscal year', function () {
    expect(AccountingPeriod::count())->toBe(12);
});

it('posts a balanced journal, numbers it and audits it', function () {
    $entry = draftEntry($this);

    $posted = app(PostJournalEntry::class)->handle($entry, $this->user, allowSelfPosting: true);

    expect($posted->status)->toBe(JournalStatus::Posted)
        ->and($posted->number)->toBe('JV-2026-000001')
        ->and(AuditLog::where('entity_type', 'journal_entry')->where('entity_id', $entry->id)->pluck('action')->all())->toBe(['created', 'posted']);
});

it('rejects an unbalanced journal on creation', function () {
    draftEntry($this, 10000, 9000);
})->throws(JournalNotBalanced::class);

it('blocks posting by the creator without the self-post permission', function () {
    app(PostJournalEntry::class)->handle(draftEntry($this), $this->user);
})->throws(InvalidJournalState::class);

it('refuses posting into a closed period', function () {
    $entry = draftEntry($this);
    AccountingPeriod::query()->whereDate('starts_on', '2026-03-01')->update(['status' => 'closed']);

    app(PostJournalEntry::class)->handle($entry, $this->user, true);
})->throws(AccountingPeriodClosed::class);

it('cannot post the same journal twice', function () {
    $entry = draftEntry($this);
    app(PostJournalEntry::class)->handle($entry, $this->user, true);

    app(PostJournalEntry::class)->handle($entry, $this->user, true);
})->throws(InvalidJournalState::class);

it('reuses the same entry for a repeated idempotency key', function () {
    $first = draftEntry($this, key: 'abc-123');
    $second = draftEntry($this, key: 'abc-123');

    expect($second->id)->toBe($first->id)->and(JournalEntry::count())->toBe(1);
});

it('reverses a posted journal with opposite lines and keeps the original', function () {
    $entry = app(PostJournalEntry::class)->handle(draftEntry($this), $this->user, true);

    $reversal = app(ReverseJournalEntry::class)->handle($entry, $this->user, CarbonImmutable::parse('2026-03-20'), 'mistake');

    $entry->refresh();
    expect($entry->status)->toBe(JournalStatus::Reversed)
        ->and($entry->reversed_by_id)->toBe($reversal->id)
        ->and($reversal->reverses_id)->toBe($entry->id)
        ->and($reversal->status)->toBe(JournalStatus::Posted)
        ->and($entry->lines()->first()->debit)->toBe(10000)
        ->and($reversal->lines()->where('account_id', $this->cash->id)->first()->credit)->toBe(10000);
});

it('cannot reverse a draft', function () {
    app(ReverseJournalEntry::class)->handle(draftEntry($this), $this->user, CarbonImmutable::parse('2026-03-20'));
})->throws(InvalidJournalState::class);

it('numbers journals sequentially', function () {
    $a = app(PostJournalEntry::class)->handle(draftEntry($this), $this->user, true);
    $b = app(PostJournalEntry::class)->handle(draftEntry($this), $this->user, true);

    expect([$a->number, $b->number])->toBe(['JV-2026-000001', 'JV-2026-000002']);
});
