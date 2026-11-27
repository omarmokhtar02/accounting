<?php

use Modules\Accounting\Domain\Enums\CashFlowCategory;

covers(CashFlowCategory::class);

it('has the categories the cash-flow statement needs', function () {
    expect(array_map(fn ($c) => $c->value, CashFlowCategory::cases()))->toBe(['none', 'cash', 'operating', 'investing', 'financing']);
});

it('labels every category in both languages', function (string $locale) {
    app()->setLocale($locale);

    foreach (CashFlowCategory::cases() as $category) {
        expect($category->label())->not->toStartWith('accounting.');
    }
})->with(['en', 'ar']);
