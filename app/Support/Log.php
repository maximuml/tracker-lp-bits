<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\SiteLogRepositoryInterface;

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
        app(SiteLogRepositoryInterface::class)->create($text, $security, $userId);
    }

    public static function writeWithContext(string $text, string $security = 'normal'): void
    {
        $user = CurrentUser::instance()->get() ?? [];

        self::write($text, $security, (int) (CurrentUser::instance()->id()));
    }
}
