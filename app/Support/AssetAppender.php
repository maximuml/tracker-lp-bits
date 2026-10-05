<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\SafeHtml;
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
            $append = trim(view('support._js-append', ['src' => $js])->render());
        } else {
            $nonce = self::cspNonce();
            $append = trim(view('support._js-append', ['content' => SafeHtml::fromTrustedHtml($js), 'nonce' => $nonce])->render());
        }
        self::appendJsCss($append, $position, $key);
    }

    public static function css(string $css, string $position, bool $isFile, ?string $key = null): void
    {
        if ($isFile) {
            $append = trim(view('support._css-append', ['src' => $css])->render());
        } else {
            $nonce = self::cspNonce();
            $append = trim(view('support._css-append', ['content' => SafeHtml::fromTrustedHtml($css), 'nonce' => $nonce])->render());
        }
        self::appendJsCss($append, $position, $key);
    }

    /**
     * Append raw markup (e.g. a `<template>` consumed by nx-layer) to a
     * position — unlike js()/css() the string is emitted verbatim.
     */
    public static function html(string $html, string $position, ?string $key = null): void
    {
        self::appendJsCss($html, $position, $key);
    }

    /**
     * Append a ?v=<filemtime> cache-buster to a local asset path so a
     * deploy picks up changed JS/CSS instead of serving heuristic-cached
     * copies. External URLs and files that do not exist pass through
     * untouched.
     */
    public static function versionedSrc(string $src): string
    {
        if (str_contains($src, '://') || str_contains($src, '?')) {
            return $src;
        }
        $path = public_path(ltrim($src, '/'));
        if (! is_file($path)) {
            return $src;
        }

        return $src.'?v='.filemtime($path);
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

    /**
     * Appended head assets as SafeHtml objects so templates can render
     * them via `{{ }}` without calling `SafeHtml::fromTrustedHtml` inline
     * (keeps the LegacyViewSurface baseline flat on modern layouts).
     *
     * @return list<SafeHtml>
     */
    public static function getAppendHeadersSafe(): array
    {
        return array_values(array_map(
            static fn (string $html): SafeHtml => SafeHtml::fromTrustedHtml($html),
            self::$appendHeaders,
        ));
    }

    /** @return list<SafeHtml> */
    public static function getAppendFootersSafe(): array
    {
        return array_values(array_map(
            static fn (string $html): SafeHtml => SafeHtml::fromTrustedHtml($html),
            self::$appendFooters,
        ));
    }

    public static function flush(): void
    {
        self::$appendHeaders = [];
        self::$appendFooters = [];
    }
}
