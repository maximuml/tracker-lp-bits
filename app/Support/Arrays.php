<?php

namespace App\Support;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Legacy array helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration — thin wrappers around Laravel's
 * `Illuminate\Support\Arr` so callers using dot-notation `arr_get()`
 * and `arr_set()` keep working while the code moves into `App\Support`.
 */
final class Arrays
{
    /**
     * @param  array<mixed, mixed>|ArrayAccess  $array
     */
    public static function get(array|ArrayAccess $array, int|string|null $key, mixed $default = null): mixed
    {
        return Arr::get($array, $key, $default);
    }

    public static function set(array &$array, int|string|null $key, mixed $value): array
    {
        return Arr::set($array, $key, $value);
    }
}
