<?php

declare(strict_types=1);

namespace App\Support;

/**
 * @deprecated Use {@see PageResponses}. Kept as a forwarding shim while
 *             call sites migrate in slices.
 */
final class LegacyResponse
{
    public static function abort(
        string $heading,
        string $text,
        bool $htmlstrip = true,
        bool $head = true,
        bool $foot = true,
    ): never {
        PageResponses::abort($heading, $text, $htmlstrip, $head, $foot);
    }

    public static function captureAbort(string $heading, string $text, bool $htmlstrip = true, string $title = ''): string
    {
        return PageResponses::captureAbort($heading, $text, $htmlstrip, $title);
    }

    public static function permissionDenied(?int $allowMinimumClass = null): never
    {
        PageResponses::permissionDenied($allowMinimumClass);
    }

    /**
     * @param  mixed  $value  Single value or array of values.
     */
    public static function assertId(
        mixed $value,
        bool $stdhead = false,
        bool $stdfoot = true,
        bool $log = true,
    ): bool {
        return PageResponses::assertId($value, $stdhead, $stdfoot, $log);
    }

    public static function canUpload(string $where = 'torrents'): bool
    {
        return PageResponses::canUpload($where);
    }

    public static function notFound(): void
    {
        PageResponses::notFound();
    }

    public static function redirect(string $url): void
    {
        PageResponses::redirect($url);
    }
}
