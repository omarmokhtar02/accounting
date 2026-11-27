<?php

namespace Modules\Accounting\Application\Import;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Strict Y-m-d parsing for import cells: "2026-02-31" and "1/2/2026" are errors, never silently shifted.
 */
final class ImportDate
{
    public static function parse(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->toDateString() === $value ? $date : null;
    }
}
