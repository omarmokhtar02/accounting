<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\Commands\ChangeAccountStatus;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;
use Modules\Shared\Domain\Models\AuditLog;
use Modules\Tenancy\Infrastructure\TenantContext;

covers(ChangeAccountStatus::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    $this->command = app(ChangeAccountStatus::class);
});

it('deactivates and reactivates an unused account', function () {
    $account = makeAccount($this->company, '9101');

    expect($this->command->handle($account, AccountStatus::Inactive)->status)->toBe(AccountStatus::Inactive)
        ->and($this->command->handle($account, AccountStatus::Active)->status)->toBe(AccountStatus::Active);
});

it('archives an account with a zero balance and never brings it back', function () {
    $account = makeAccount($this->company, '9102');
    $this->command->handle($account, AccountStatus::Archived);

    expect($account->fresh()->status)->toBe(AccountStatus::Archived);

    $this->command->handle($account, AccountStatus::Active);
})->throws(InvalidAccountChange::class);

it('refuses to archive an account that still has a balance', function () {
    openFiscalYear2026();
    $account = makeAccount($this->company, '9104');
    $entry = app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse('2026-03-15'), 'Seed balance',
        [new JournalLineData($account->id, 5000, 0), new JournalLineData(accountByRole(AccountRole::SalesRevenue)->id, 0, 5000)],
    ));
    app(PostJournalEntry::class)->handle($entry, $this->user, allowSelfPosting: true);

    $this->command->handle($account, AccountStatus::Archived);
})->throws(InvalidAccountChange::class);

it('refuses to deactivate an account that plays a role', function () {
    $this->command->handle(accountByRole(AccountRole::Cash), AccountStatus::Inactive);
})->throws(InvalidAccountChange::class);

it('refuses to deactivate a header that still has live sub-accounts', function () {
    $header = $this->company->accounts()->where('code', '12')->first();

    $this->command->handle($header, AccountStatus::Inactive);
})->throws(InvalidAccountChange::class);

it('writes an audit entry with the old and new status', function () {
    $account = makeAccount($this->company, '9103');
    $this->command->handle($account, AccountStatus::Inactive);

    $log = AuditLog::where('entity_type', 'account')->where('entity_id', $account->id)->where('action', 'status_changed')->first();

    expect($log->old_values)->toBe(['status' => 'active'])->and($log->new_values)->toBe(['status' => 'inactive'])
        ->and($log->company_id)->toBe(app(TenantContext::class)->companyId());
});
