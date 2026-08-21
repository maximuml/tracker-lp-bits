<?php

namespace App\Support;

use Nexus\Plugin\Hook;

/**
 * Legacy hook/action helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `add_filter()`, `apply_filter()`, `add_action()` and `do_action()`.
 * Uses the container-bound Hook singleton when available, falling back to the
 * value stored in SupportContext for non-Laravel callers (e.g. plain PHPUnit).
 */
final class Hooks
{
    public static function addFilter(string $name, callable $function, int $priority = 10, int $argc = 1): void
    {
        self::hook()->addFilter($name, $function, $priority, $argc);
    }

    public static function applyFilter(string $name, mixed ...$args): mixed
    {
        return self::hook()->applyFilter($name, ...$args);
    }

    public static function addAction(string $name, callable $function, int $priority = 10, int $argc = 1): void
    {
        self::hook()->addAction($name, $function, $priority, $argc);
    }

    public static function doAction(string $name, mixed ...$args): mixed
    {
        return self::hook()->doAction($name, ...$args);
    }

    private static function hook(): Hook
    {
        if (function_exists('app')) {
            try {
                return app(Hook::class);
            } catch (\Throwable $e) {
                // fall back to the legacy context value
            }
        }

        $hook = SupportContext::getGlobal('hook');

        return $hook instanceof Hook ? $hook : new Hook;
    }
}
