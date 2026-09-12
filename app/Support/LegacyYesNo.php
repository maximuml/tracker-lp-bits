<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tolerant check for columns migrated from enum('yes','no') to tinyint(1).
 *
 * Raw DB rows (e.g. CurrentUser arrays built without model casts) now carry
 * 1/0 while legacy code compared against the 'yes'/'no' strings. Accepts
 * both representations so either source works.
 */
final class LegacyYesNo
{
    public static function isYes(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'yes';
    }

    public static function isNo(mixed $value): bool
    {
        return $value === false || $value === 0 || $value === '0' || $value === 'no';
    }
}
