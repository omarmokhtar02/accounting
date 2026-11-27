<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Application\DTOs\UpdateAccountData;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;

covers(JournalLineData::class, CreateJournalEntryData::class, UpdateAccountData::class);

it('carries a journal line', function () {
    $line = new JournalLineData(5, 100, 0, 'memo');

    expect($line->accountId)->toBe(5)->and($line->debit)->toBe(100)->and($line->credit)->toBe(0)->and($line->description)->toBe('memo');
});

it('defaults the optional journal line description to null', function () {
    expect((new JournalLineData(5, 0, 100))->description)->toBeNull();
});

it('carries a journal entry with its optional source and idempotency key', function () {
    $date = CarbonImmutable::parse('2026-03-15');
    $data = new CreateJournalEntryData($date, 'Sale', [new JournalLineData(1, 10, 0), new JournalLineData(2, 0, 10)], 'k', 'sales_invoice', 77);

    expect($data->entryDate)->toBe($date)->and($data->description)->toBe('Sale')->and($data->lines)->toHaveCount(2)
        ->and($data->idempotencyKey)->toBe('k')->and($data->sourceType)->toBe('sales_invoice')->and($data->sourceId)->toBe(77);

    $minimal = new CreateJournalEntryData($date, 'x', []);
    expect($minimal->idempotencyKey)->toBeNull()->and($minimal->sourceType)->toBeNull()->and($minimal->sourceId)->toBeNull();
});

it('carries account updates', function () {
    $data = new UpdateAccountData('1101', 'Cash', 'نقدية', AccountType::Asset, 3, true, CashFlowCategory::Cash);

    expect($data->code)->toBe('1101')->and($data->nameAr)->toBe('نقدية')->and($data->parentId)->toBe(3)
        ->and($data->isPostable)->toBeTrue()->and($data->cashFlowCategory)->toBe(CashFlowCategory::Cash);
});

it('is immutable', function () {
    $line = new JournalLineData(1, 1, 0);

    $line->debit = 5;
})->throws(Error::class);
