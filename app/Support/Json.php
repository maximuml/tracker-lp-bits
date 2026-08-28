<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Legacy JSON helper extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration. Wraps `json_encode()` with the same
 * flags used throughout the legacy code (`JSON_UNESCAPED_UNICODE` |
 * `JSON_UNESCAPED_SLASHES`).
 */
final class Json
{
    public static function encode(mixed $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
