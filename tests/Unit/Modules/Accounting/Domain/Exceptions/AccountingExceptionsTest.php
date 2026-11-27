<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\AccountingPeriodClosed;
use Modules\Accounting\Domain\Exceptions\AccountRoleError;
use Modules\Accounting\Domain\Exceptions\ChartAlreadyExists;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Accounting\Domain\Exceptions\ExchangeRateError;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;
use Modules\Accounting\Domain\Exceptions\InvalidChartTemplate;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;
use Modules\Accounting\Domain\Exceptions\JournalNotBalanced;

covers(
    AccountingPeriodClosed::class, AccountRoleError::class, ChartAlreadyExists::class, InvalidAccountChange::class,
    InvalidChartTemplate::class, InvalidJournalState::class, ExchangeRateError::class, JournalNotBalanced::class, DomainException::class,
);

it('translates every domain failure into the user language', function (callable $make) {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);
        $exception = $make();

        expect($exception)->toBeInstanceOf(DomainException::class)
            ->and($exception->userMessage())->not->toStartWith('accounting.errors.')->not->toBeEmpty()
            ->and($exception->translationKey)->toStartWith('accounting.errors.');
    }
})->with([
    'journal not balanced' => [fn () => JournalNotBalanced::totals(1000, 900)],
    'too few lines' => [fn () => JournalNotBalanced::tooFewLines()],
    'period closed' => [fn () => AccountingPeriodClosed::forDate('2026-03-15')],
    'period missing' => [fn () => AccountingPeriodClosed::notFound('2026-03-15')],
    'invalid transition' => [fn () => InvalidJournalState::transition(JournalStatus::Draft, JournalStatus::Reversed)],
    'invalid account' => [fn () => InvalidJournalState::invalidAccount()],
    'unposted drafts' => [fn () => InvalidJournalState::unpostedDrafts()],
    'self posting' => [fn () => InvalidJournalState::selfPosting()],
    'status transition' => [fn () => InvalidAccountChange::statusTransition(AccountStatus::Archived, AccountStatus::Active)],
    'locked after use' => [fn () => InvalidAccountChange::lockedAfterUse('code')],
    'archive with balance' => [fn () => InvalidAccountChange::archiveWithBalance()],
    'parent cycle' => [fn () => InvalidAccountChange::parentCycle()],
    'has children' => [fn () => InvalidAccountChange::hasChildren()],
    'parent not allowed' => [fn () => InvalidAccountChange::parentNotAllowed()],
    'role not mapped' => [fn () => AccountRoleError::notMapped(AccountRole::Cash)],
    'role wrong type' => [fn () => AccountRoleError::wrongType(AccountRole::Cash, AccountType::Asset)],
    'role not postable' => [fn () => AccountRoleError::notPostable()],
    'control account' => [fn () => AccountRoleError::controlAccountManualPosting('1203')],
    'rate missing' => [fn () => ExchangeRateError::missing('USD', '2026-03-15')],
    'rate base currency' => [fn () => ExchangeRateError::baseCurrency('EGP')],
    'foreign line invalid' => [fn () => ExchangeRateError::invalidForeignLine()],
    'chart exists' => [fn () => ChartAlreadyExists::forCompany()],
]);

it('puts the amounts and dates into the message', function () {
    app()->setLocale('en');

    expect(JournalNotBalanced::totals(100050, 90000)->userMessage())->toContain('1,000.50')->toContain('900.00')
        ->and(AccountingPeriodClosed::forDate('2026-03-15')->userMessage())->toContain('2026-03-15')
        ->and(AccountRoleError::controlAccountManualPosting('1203')->userMessage())->toContain('1203')
        ->and(InvalidAccountChange::lockedAfterUse('code')->userMessage())->toContain('code');
});

it('keeps the technical translation key for logging', function () {
    expect(JournalNotBalanced::tooFewLines()->getMessage())->toBe('accounting.errors.journal_too_few_lines');
});

it('lists every problem of a broken chart template', function () {
    $exception = new InvalidChartTemplate(['Duplicate code 1', 'Unknown parent 9']);

    expect($exception->problems)->toBe(['Duplicate code 1', 'Unknown parent 9'])
        ->and($exception->getMessage())->toContain('Duplicate code 1')->toContain('Unknown parent 9');
});
