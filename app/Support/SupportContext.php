<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * Static facade for the per-request NexusContext value object.
 *
 * Legacy helpers and Blade/PHP partials still call these static methods while
 * the implementation lives in NexusContext. The context must be populated
 * explicitly via fromRequest(); no PHP superglobals or $GLOBALS are used.
 */
final class SupportContext
{
    public static function fromRequest(Request $request): void
    {
        self::context()->setFromRequest($request);
    }

    public static function reset(): void
    {
        Container::getInstance()->instance(NexusContext::class, new NexusContext);
    }

    public static function getContext(): NexusContext
    {
        return self::context();
    }

    private static function context(): NexusContext
    {
        if (! Container::getInstance()->bound(NexusContext::class)) {
            Container::getInstance()->instance(NexusContext::class, new NexusContext);
        }

        return app(NexusContext::class);
    }

    /** @param array<string, mixed>|null $user */
    public static function setUser(?array $user): void
    {
        self::context()->setUser($user);
    }

    /** @return array<string, mixed>|null */
    public static function getUser(): ?array
    {
        return self::context()->getUser();
    }

    /**
     * Return a reference to the legacy per-request user update set.
     *
     * @return array<string, mixed>
     */
    public static function &getUserUpdateSet(): array
    {
        return self::context()->userUpdateSet;
    }

    public static function addUserUpdate(string $key, mixed $value): void
    {
        self::context()->addUserUpdate($key, $value);
    }

    public static function getServerValue(string $key, mixed $default = null): mixed
    {
        return self::context()->getServerValue($key, $default);
    }

    public static function getCookieValue(string $key, ?string $default = null): ?string
    {
        return self::context()->getCookieValue($key, $default);
    }

    public static function getQuery(string $key, mixed $default = null): mixed
    {
        return self::context()->getQuery($key, $default);
    }
}
