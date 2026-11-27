<?php

use Modules\Accounting\Application\Commands\ClosePeriod;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Models\AccountingPeriod;
use Modules\Shared\Domain\Models\AuditLog;

covers(ClosePeriod::class);

beforeEach(function () {
    ['user' => $this->user] = bootstrapTenant();
    openFiscalYear2026();
    $this->march = AccountingPeriod::whereDate('starts_on', '2026-03-01')->first();
});

it('closes an open period and records who and when', function () {
    $period = app(ClosePeriod::class)->handle($this->march, $this->user);

    expect($period->status)->toBe(AccountingPeriod::STATUS_CLOSED)
        ->and($period->closed_by)->toBe($this->user->id)
        ->and($period->closed_at)->not->toBeNull()
        ->and($period->isOpen())->toBeFalse();
});

it('cannot close a period that still has unposted drafts', function () {
    draftJournal(1000, '2026-03-20');

    app(ClosePeriod::class)->handle($this->march, $this->user);
})->throws(InvalidJournalState::class);

it('ignores drafts dated in other periods', function () {
    draftJournal(1000, '2026-04-20');

    expect(app(ClosePeriod::class)->handle($this->march, $this->user)->status)->toBe('closed');
});

it('allows closing once the drafts are posted', function () {
    postedJournal($this->user, 1000, '2026-03-20');

    expect(app(ClosePeriod::class)->handle($this->march, $this->user)->isOpen())->toBeFalse();
});

it('audits the closing', function () {
    app(ClosePeriod::class)->handle($this->march, $this->user);

    expect(AuditLog::where('entity_type', 'accounting_period')->where('action', 'closed')->count())->toBe(1);
});
