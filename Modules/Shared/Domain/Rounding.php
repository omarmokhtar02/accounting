<?php

namespace Modules\Shared\Domain;

/**
 * The single place where fractions of a minor unit are rounded: half away from zero.
 * Operates on decimal strings with bcmath so no float ever touches an amount.
 */
final class Rounding
{
    /** Internal working scale for bcmath intermediate results. */
    public const SCALE = 12;

    public static function toInteger(string $decimal): int
    {
        $negative = str_starts_with($decimal, '-');
        $absolute = ltrim($decimal, '-');

        // Add 0.5 then truncate: half-up on the absolute value == half away from zero.
        $rounded = bcadd($absolute, '0.5', self::SCALE);
        $integer = (int) bcadd(explode('.', $rounded)[0], '0', 0);

        return $negative ? -$integer : $integer;
    }
}
