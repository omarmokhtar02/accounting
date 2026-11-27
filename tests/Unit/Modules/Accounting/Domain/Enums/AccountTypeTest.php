<?php

use Modules\Accounting\Domain\Enums\AccountType;

covers(AccountType::class);

it('has the five fundamental account types', function () {
    expect(array_map(fn ($t) => $t->value, AccountType::cases()))->toBe(['asset', 'liability', 'equity', 'revenue', 'expense']);
});

it('labels every type in both languages', function (string $locale, array $expected) {
    app()->setLocale($locale);

    expect(array_map(fn ($t) => $t->label(), AccountType::cases()))->toBe($expected);
})->with([
    ['en', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']],
    ['ar', ['أصول', 'خصوم', 'حقوق ملكية', 'إيرادات', 'مصروفات']],
]);

it('builds from its stored value', function () {
    expect(AccountType::from('revenue'))->toBe(AccountType::Revenue)->and(AccountType::tryFrom('nope'))->toBeNull();
});
