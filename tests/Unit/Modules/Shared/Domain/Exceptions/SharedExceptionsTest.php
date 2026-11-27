<?php

use Modules\Shared\Domain\Exceptions\CurrencyMismatch;
use Modules\Shared\Domain\Exceptions\InvalidImportFile;
use Modules\Shared\Domain\Exceptions\InvalidMoneyAmount;
use Modules\Shared\Domain\Exceptions\UnknownCurrency;

covers(CurrencyMismatch::class, InvalidImportFile::class, InvalidMoneyAmount::class, UnknownCurrency::class);

it('describes a currency mismatch', function () {
    expect(CurrencyMismatch::between('EGP', 'USD')->getMessage())->toContain('EGP')->toContain('USD');
});

it('describes invalid amounts', function () {
    expect(InvalidMoneyAmount::notANumber('abc')->getMessage())->toContain('abc')
        ->and(InvalidMoneyAmount::tooManyDecimals('1.234', 'EGP', 2)->getMessage())->toContain('2')->toContain('EGP')
        ->and(InvalidMoneyAmount::invalidRate('0')->getMessage())->toContain('0')
        ->and(InvalidMoneyAmount::emptyAllocation()->getMessage())->not->toBeEmpty();
});

it('names the unknown currency', function () {
    expect(UnknownCurrency::forCode('ZZZ')->getMessage())->toContain('ZZZ');
});

it('translates import file problems into both languages', function (InvalidImportFile $e) {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        expect($e->userMessage())->not->toStartWith('app.import.')->not->toBeEmpty();
    }
})->with([
    'unreadable' => [fn () => InvalidImportFile::unreadable()],
    'empty' => [fn () => InvalidImportFile::empty()],
    'too many' => [fn () => InvalidImportFile::tooManyRows(5000)],
    'missing' => [fn () => InvalidImportFile::missingColumns(['code', 'name'])],
]);
