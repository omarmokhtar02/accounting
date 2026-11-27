<?php

use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Exceptions\InvalidJournalState;

covers(JournalStatus::class);

it('allows only forward transitions', function (JournalStatus $from, JournalStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [JournalStatus::Draft, JournalStatus::Posted, true],
    [JournalStatus::Draft, JournalStatus::Reversed, false],
    [JournalStatus::Draft, JournalStatus::Draft, false],
    [JournalStatus::Posted, JournalStatus::Reversed, true],
    [JournalStatus::Posted, JournalStatus::Draft, false],
    [JournalStatus::Posted, JournalStatus::Posted, false],
    [JournalStatus::Reversed, JournalStatus::Draft, false],
    [JournalStatus::Reversed, JournalStatus::Posted, false],
    [JournalStatus::Reversed, JournalStatus::Reversed, false],
]);

it('throws on an invalid transition and passes on a valid one', function () {
    JournalStatus::Draft->assertCanTransitionTo(JournalStatus::Posted);

    expect(fn () => JournalStatus::Draft->assertCanTransitionTo(JournalStatus::Reversed))->toThrow(InvalidJournalState::class);
});

it('maps each status to a badge tone', function () {
    expect(JournalStatus::Draft->tone())->toBe('warning')
        ->and(JournalStatus::Posted->tone())->toBe('success')
        ->and(JournalStatus::Reversed->tone())->toBe('neutral');
});

it('has a translated label in both languages', function (string $locale) {
    app()->setLocale($locale);

    foreach (JournalStatus::cases() as $status) {
        expect($status->label())->not->toStartWith('accounting.');
    }
})->with(['en', 'ar']);
