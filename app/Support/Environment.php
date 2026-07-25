<?php

namespace App\Support;

/**
 * Runtime environment helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 */
final class Environment
{
    public static function isConsole(): bool
    {
        return (!defined('RUNNING_IN_OCTANE') || !RUNNING_IN_OCTANE) && PHP_SAPI === 'cli';
    }

    public static function isWindows(): bool
    {
        return (!defined('RUNNING_IN_OCTANE') || !RUNNING_IN_OCTANE) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public static function isFpm(): bool
    {
        return php_sapi_name() === 'fpm-fcgi';
    }

    public static function commandExists(string $command): bool
    {
        return trim((string) exec("command -v $command")) !== '';
    }
}
