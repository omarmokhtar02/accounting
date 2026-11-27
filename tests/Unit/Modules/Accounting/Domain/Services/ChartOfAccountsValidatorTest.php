<?php

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Exceptions\InvalidChartTemplate;
use Modules\Accounting\Domain\Services\ChartOfAccountsValidator;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;

covers(ChartOfAccountsValidator::class);

function seed(string $code, ?string $parent = null, bool $postable = true, AccountType $type = AccountType::Asset, ?AccountRole $role = null, string $en = 'Name', string $ar = 'اسم'): AccountSeed
{
    return new AccountSeed($code, $en, $ar, $type, $parent, $postable, role: $role);
}

beforeEach(function () {
    $this->validator = new ChartOfAccountsValidator;
});

it('accepts a valid template', function () {
    $seeds = [seed('1', null, false), seed('1101', '1', true, AccountType::Asset, AccountRole::Cash)];

    expect($this->validator->problems($seeds, [AccountRole::Cash]))->toBe([]);
});

it('reports duplicate and empty codes', function () {
    $problems = $this->validator->problems([seed('1'), seed('1'), seed('')]);

    expect($problems)->toContain('Duplicate code 1')->and(implode('|', $problems))->toContain("Invalid code ''");
});

it('reports codes that are too long', function () {
    expect(implode('|', $this->validator->problems([seed(str_repeat('9', 31))])))->toContain('Invalid code');
});

it('requires both language names', function () {
    $problems = implode('|', $this->validator->problems([seed('1', en: ''), seed('2', ar: '  ')]));

    expect($problems)->toContain('Account 1 needs both')->toContain('Account 2 needs both');
});

it('reports unknown parents', function () {
    expect(implode('|', $this->validator->problems([seed('11', '1')])))->toContain('unknown parent 1');
});

it('requires a parent to have the same type and not be postable', function () {
    $problems = implode('|', $this->validator->problems([
        seed('1', null, false, AccountType::Asset), seed('11', '1', true, AccountType::Liability),
        seed('2', null, true, AccountType::Asset), seed('21', '2', true, AccountType::Asset),
    ]));

    expect($problems)->toContain('same type as its parent')->toContain('Parent 2 of 21 must not be postable');
});

it('detects a parent cycle', function () {
    $problems = implode('|', $this->validator->problems([seed('1', '2', false), seed('2', '1', false)]));

    expect($problems)->toContain('parent cycle');
});

it('reports a role mapped twice', function () {
    $problems = implode('|', $this->validator->problems([
        seed('1', null, true, AccountType::Asset, AccountRole::Cash), seed('2', null, true, AccountType::Asset, AccountRole::Cash),
    ]));

    expect($problems)->toContain('Role cash is mapped twice');
});

it('requires a role account to have the expected type and be postable', function () {
    $wrongType = implode('|', $this->validator->problems([seed('1', null, true, AccountType::Liability, AccountRole::Cash)]));
    $header = implode('|', $this->validator->problems([seed('1', null, false, AccountType::Asset, AccountRole::Cash)]));

    expect($wrongType)->toContain('requires type asset')->and($header)->toContain('must be postable');
});

it('reports missing required roles', function () {
    $problems = $this->validator->problems([seed('1')], [AccountRole::Cash, AccountRole::Bank]);

    expect($problems)->toContain('Required role cash is not mapped to any account')->toContain('Required role bank is not mapped to any account');
});

it('throws with every problem when asserting', function () {
    try {
        $this->validator->assertValid([seed('1'), seed('1')], [AccountRole::Cash]);
        $this->fail('expected exception');
    } catch (InvalidChartTemplate $e) {
        expect($e->problems)->toContain('Duplicate code 1')->and(count($e->problems))->toBeGreaterThanOrEqual(2);
    }
});

it('does not throw for a valid template', function () {
    $this->validator->assertValid([seed('1')]);

    expect(true)->toBeTrue();
});
