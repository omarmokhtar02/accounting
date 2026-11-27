<?php

namespace Modules\Accounting\Domain\Services;

use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Exceptions\InvalidChartTemplate;
use Modules\Accounting\Domain\ValueObjects\AccountSeed;

/**
 * Pure structural checks for a chart-of-accounts template. Used when a country pack is applied
 * and by the shared contract test every pack must pass.
 */
final class ChartOfAccountsValidator
{
    /**
     * @param  list<AccountSeed>  $seeds
     * @param  list<AccountRole>  $requiredRoles
     * @return list<string> human readable problems (empty when the template is valid)
     */
    public function problems(array $seeds, array $requiredRoles = []): array
    {
        $problems = [];
        $byCode = [];

        foreach ($seeds as $seed) {
            if ($seed->code === '' || strlen($seed->code) > 30) {
                $problems[] = "Invalid code '{$seed->code}'";
            }

            if (isset($byCode[$seed->code])) {
                $problems[] = "Duplicate code {$seed->code}";
            }

            $byCode[$seed->code] = $seed;

            if (trim($seed->nameEn) === '' || trim($seed->nameAr) === '') {
                $problems[] = "Account {$seed->code} needs both an English and an Arabic name";
            }
        }

        $roles = [];

        foreach ($seeds as $seed) {
            if ($seed->parentCode !== null) {
                $parent = $byCode[$seed->parentCode] ?? null;

                if ($parent === null) {
                    $problems[] = "Account {$seed->code} has unknown parent {$seed->parentCode}";
                } else {
                    if ($parent->type !== $seed->type) {
                        $problems[] = "Account {$seed->code} must have the same type as its parent {$parent->code}";
                    }

                    if ($parent->postable) {
                        $problems[] = "Parent {$parent->code} of {$seed->code} must not be postable";
                    }
                }
            }

            if ($seed->role !== null) {
                if (isset($roles[$seed->role->value])) {
                    $problems[] = "Role {$seed->role->value} is mapped twice ({$roles[$seed->role->value]} and {$seed->code})";
                }

                $roles[$seed->role->value] = $seed->code;

                if ($seed->type !== $seed->role->expectedType()) {
                    $problems[] = "Account {$seed->code} has role {$seed->role->value} which requires type {$seed->role->expectedType()->value}";
                }

                if (! $seed->postable) {
                    $problems[] = "Account {$seed->code} has role {$seed->role->value} so it must be postable";
                }
            }
        }

        foreach ($requiredRoles as $required) {
            if (! isset($roles[$required->value])) {
                $problems[] = "Required role {$required->value} is not mapped to any account";
            }
        }

        $problems = [...$problems, ...$this->cycles($byCode)];

        return $problems;
    }

    /**
     * @param  list<AccountSeed>  $seeds
     * @param  list<AccountRole>  $requiredRoles
     *
     * @throws InvalidChartTemplate
     */
    public function assertValid(array $seeds, array $requiredRoles = []): void
    {
        $problems = $this->problems($seeds, $requiredRoles);

        if ($problems !== []) {
            throw new InvalidChartTemplate($problems);
        }
    }

    /**
     * @param  array<string, AccountSeed>  $byCode
     * @return list<string>
     */
    private function cycles(array $byCode): array
    {
        $problems = [];

        foreach ($byCode as $code => $seed) {
            $seen = [$code => true];
            $cursor = $seed;

            while ($cursor->parentCode !== null && isset($byCode[$cursor->parentCode])) {
                if (isset($seen[$cursor->parentCode])) {
                    $problems[] = "Account {$code} is part of a parent cycle";
                    break;
                }

                $seen[$cursor->parentCode] = true;
                $cursor = $byCode[$cursor->parentCode];
            }
        }

        return $problems;
    }
}
