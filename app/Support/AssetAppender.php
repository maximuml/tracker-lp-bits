<?php

declare(strict_types=1);

namespace App\Support;

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
            $append = sprintf('<script type="text/javascript">%s</script>', $js);
        }
        self::appendJsCss($append, $position, $key);
    }

    public static function css(string $css, string $position, bool $isFile, ?string $key = null): void
    {
        if ($isFile) {
            $append = sprintf('<link rel="stylesheet" href="%s" type="text/css">', $css);
        } else {
            $append = sprintf('<style type="text/css">%s</style>', $css);
        }
        self::appendJsCss($append, $position, $key);
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
