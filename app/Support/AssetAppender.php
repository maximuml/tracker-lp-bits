<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class AssetAppender
{
    /** @var array<string, string> */
    private static array $appendHeaders = [];

    /** @var array<string, string> */
    private static array $appendFooters = [];

    public static function js(string $js, string $position, bool $isFile, ?string $key = null): void
    {
        if ($isFile) {
            $append = sprintf('<script type="text/javascript" src="%s"></script>', $js);
        } else {
            $nonce = self::cspNonce();
            $append = sprintf('<script type="text/javascript" nonce="%s">%s</script>', $nonce, $js);
        }
        self::appendJsCss($append, $position, $key);
    }

    public static function css(string $css, string $position, bool $isFile, ?string $key = null): void
    {
        if ($isFile) {
            $append = sprintf('<link rel="stylesheet" href="%s" type="text/css">', $css);
        } else {
            $nonce = self::cspNonce();
            $append = sprintf('<style type="text/css" nonce="%s">%s</style>', $nonce, $css);
        }
        self::appendJsCss($append, $position, $key);
    }

    /**
     * Get the CSP nonce from the current request, or empty string if unavailable.
     */
    private static function cspNonce(): string
    {
        $request = app()->bound('request') ? app('request') : null;
        if ($request instanceof Request) {
            return (string) $request->attributes->get('csp_nonce', '');
        }

        return '';
    }

    private static function appendJsCss(string $append, string $position, ?string $key = null): void
    {
        $log = "position: $position, key: $key";
        if ($key === null) {
            $key = md5($append);
            $log .= ", md5 key: $key";
        }
        if ($position == 'header') {
            if (! isset(self::$appendHeaders[$key])) {
                self::$appendHeaders[$key] = $append;
            } else {
                Logger::writeWithContext((string) "{$log}, [DUPLICATE]", (string) 'info', (bool) false);
            }
        } elseif ($position == 'footer') {
            if (! isset(self::$appendFooters[$key])) {
                self::$appendFooters[$key] = $append;
            } else {
                Logger::writeWithContext((string) "{$log}, [DUPLICATE]", (string) 'info', (bool) false);
            }
        } else {
            throw new \InvalidArgumentException("Invalid position: $position");
        }
    }

    /** @return array<string, string> */
    public static function getAppendHeaders(): array
    {
        return self::$appendHeaders;
    }

    /** @return array<string, string> */
    public static function getAppendFooters(): array
    {
        return self::$appendFooters;
    }

    public static function flush(): void
    {
        self::$appendHeaders = [];
        self::$appendFooters = [];
    }
}
