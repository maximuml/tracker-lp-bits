<?php

namespace App\Support;

/**
 * Legacy hook/action helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `add_filter()`, `apply_filter()`, `add_action()` and `do_action()`.
 * The global `$hook` instance is still managed by the legacy bootstrap.
 */
final class Hooks
{
    public static function addFilter(string $name, callable $function, int $priority = 10, int $argc = 1): void
    {
        global $hook;
        $hook->addFilter($name, $function, $priority, $argc);
    }

    public static function applyFilter(string $name, mixed ...$args): mixed
    {
        global $hook;
        return $hook->applyFilter($name, ...$args);
    }

    public static function addAction(string $name, callable $function, int $priority = 10, int $argc = 1): void
    {
        global $hook;
        $hook->addAction($name, $function, $priority, $argc);
    }

    public static function doAction(string $name, mixed ...$args): mixed
    {
        global $hook;
        return $hook->doAction($name, ...$args);
    }
}
