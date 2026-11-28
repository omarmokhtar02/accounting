<?php

use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Accounting\Domain\Models\JournalLine;
use Modules\Shared\Domain\Models\AuditLog;

covers(PostJournalEntry::class);

beforeEach(function () {
    ['user' => $this->user] = bootstrapTenant();
    openFiscalYear2026();
});

it('posts a draft: status, number, period, poster and time', function () {
    $entry = app(PostJournalEntry::class)->handle(draftJournal(), $this->user, allowSelfPosting: true);

    expect($entry->status)->toBe(JournalStatus::Posted)
        ->and($entry->number)->toBe('JV-2026-000001')
        ->and($entry->posted_by)->toBe($this->user->id)
        ->and($entry->posted_at)->not->toBeNull()
        ->and($entry->accounting_period_id)->toBe(AccountingPeriod::whereDate('starts_on', '2026-03-01')->value('id'));
});

it('numbers entries sequentially per year', function () {
    $a = app(PostJournalEntry::class)->handle(draftJournal(), $this->user, true);
    $b = app(PostJournalEntry::class)->handle(draftJournal(), $this->user, true);

    expect([$a->number, $b->number])->toBe(['JV-2026-000001', 'JV-2026-000002']);
});

it('enforces segregation of duties unless the poster may post their own', function () {
    $entry = draftJournal();

    expect(fn () => app(PostJournalEntry::class)->handle($entry, $this->user))->toThrow(InvalidJournalState::class);
    expect(app(PostJournalEntry::class)->handle($entry, $this->user, true)->status)->toBe(JournalStatus::Posted);
});

it('lets a different user post without the override', function () {
    $other = bootstrapTenantUser();
    $entry = draftJournal();

    expect(app(PostJournalEntry::class)->handle($entry, $other)->posted_by)->toBe($other->id);
});

it('cannot post twice', function () {
    $entry = draftJournal();
    app(PostJournalEntry::class)->handle($entry, $this->user, true);

    app(PostJournalEntry::class)->handle($entry, $this->user, true);
})->throws(InvalidJournalState::class);

it('refuses a closed period and a date outside any period', function () {
    $entry = draftJournal(100, '2026-03-15');
    AccountingPeriod::whereDate('starts_on', '2026-03-01')->update(['status' => 'closed']);

    expect(fn () => app(PostJournalEntry::class)->handle($entry, $this->user, true))->toThrow(AccountingPeriodClosed::class);

    $outside = draftJournal(100, '2027-03-15');
    expect(fn () => app(PostJournalEntry::class)->handle($outside, $this->user, true))->toThrow(AccountingPeriodClosed::class);
});

it('re-checks the balance at posting time', function () {
    $entry = draftJournal(1000);
    JournalLine::where('journal_entry_id', $entry->id)->where('credit', '>', 0)->update(['credit' => 900]);

    app(PostJournalEntry::class)->handle($entry, $this->user, true);
})->throws(JournalNotBalanced::class);

it('leaves the entry a draft when posting fails', function () {
    $entry = draftJournal(100, '2027-03-15');

    try {
        app(PostJournalEntry::class)->handle($entry, $this->user, true);
    } catch (AccountingPeriodClosed) {
    }

    expect($entry->fresh()->status)->toBe(JournalStatus::Draft)->and($entry->fresh()->number)->toBeNull();
});

it('audits the posting with the assigned number', function () {
    $entry = app(PostJournalEntry::class)->handle(draftJournal(), $this->user, true);

    $log = AuditLog::where('entity_type', 'journal_entry')->where('entity_id', $entry->id)->where('action', 'posted')->first();

    expect($log->new_values)->toEqual(['status' => 'posted', 'number' => 'JV-2026-000001']);
});
