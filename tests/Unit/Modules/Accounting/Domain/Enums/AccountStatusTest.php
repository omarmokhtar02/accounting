<?php

use Modules\Accounting\Domain\Enums\AccountStatus;
use Modules\Accounting\Domain\Exceptions\InvalidAccountChange;

covers(AccountStatus::class);

it('allows only the documented transitions', function (AccountStatus $from, AccountStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [AccountStatus::Active, AccountStatus::Inactive, true],
    [AccountStatus::Active, AccountStatus::Archived, true],
    [AccountStatus::Active, AccountStatus::Active, false],
    [AccountStatus::Inactive, AccountStatus::Active, true],
    [AccountStatus::Inactive, AccountStatus::Archived, true],
    [AccountStatus::Inactive, AccountStatus::Inactive, false],
    [AccountStatus::Archived, AccountStatus::Active, false],
    [AccountStatus::Archived, AccountStatus::Inactive, false],
    [AccountStatus::Archived, AccountStatus::Archived, false],
]);

it('throws on a forbidden transition', function () {
    AccountStatus::Archived->assertCanTransitionTo(AccountStatus::Active);
})->throws(InvalidAccountChange::class);

it('passes silently on an allowed transition', function () {
    AccountStatus::Active->assertCanTransitionTo(AccountStatus::Inactive);

    expect(true)->toBeTrue();
});

it('labels every status in both languages', function (string $locale) {
    app()->setLocale($locale);

    foreach (AccountStatus::cases() as $status) {
        expect($status->label())->not->toStartWith('accounting.');
    }
})->with(['en', 'ar']);
