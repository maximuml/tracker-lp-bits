<?php

declare(strict_types=1);

namespace App\Support;

/**
 * User-safe exception message.
 *
 * Domain exceptions carry translated, user-facing text the legacy UI
 * displays directly. Infrastructure failures — PDO/QueryException (SQL +
 * bindings), RedisException (connection strings), PHP engine Errors —
 * must never reach a response body; collapse them to a generic message.
 * The full detail still goes to the log via each call site's logger.
 */
final class SafeExceptionMessage
{
    public static function message(\Throwable $exception): string
    {
        if ($exception instanceof \PDOException
            || $exception instanceof \RedisException
            || $exception instanceof \Error
        ) {
            return 'Internal error';
        }

        return $exception->getMessage();
    }
}
