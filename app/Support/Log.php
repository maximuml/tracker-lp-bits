<?php

namespace App\Support;

use App\Models\SiteLog;

/**
 * Legacy site-log helper extracted from `include/functions.php`.
 *
 * Backs the `write_log()` procedural wrapper. Accepts an explicit
 * user id so the wrapper can pass `get_user_id()`; if none is
 * supplied the insert records `0`.
 */
final class Log
{
    public static function write(string $text, string $security = 'normal', ?int $userId = null): void
    {
        SiteLog::query()->insert([
            'added' => now(),
            'txt' => $text,
            'security_level' => $security,
            'uid' => $userId ?? 0,
        ]);
    }

    public static function writeWithContext(string $text, string $security = 'normal'): void
    {
        $user = \App\Support\SupportContext::getUser() ?? [];

        self::write($text, $security, (int) ($user['id'] ?? 0));
    }
}
