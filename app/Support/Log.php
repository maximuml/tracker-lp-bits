<?php

namespace App\Support;

use App\Repositories\SiteLogRepository;

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
        SiteLogRepository::create($text, $security, $userId);
    }

    public static function writeWithContext(string $text, string $security = 'normal'): void
    {
        $user = app(CurrentUser::class)->get() ?? [];

        self::write($text, $security, (int) ($user['id'] ?? 0));
    }
}
