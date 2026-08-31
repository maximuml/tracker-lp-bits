<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * SafeHtml — boundary sanitizer for user-supplied HTML.
 *
 * Wraps symfony/html-sanitizer (already installed as a Filament dependency)
 * to provide a single, audited entry point for sanitizing HTML before it
 * is rendered via `{!! !!}` in Blade templates.
 *
 * Step 24 of the modernization plan.
 *
 * Usage:
 *   $clean = SafeHtml::sanitize($dirty);          // strict (default)
 *   $clean = SafeHtml::sanitize($dirty, 'media'); // media-info preset
 *
 * Presets:
 *   - 'strict'  (default): text-level elements only (b, i, em, strong, code,
 *     pre, br, span, a, ul, ol, li, p). No images, no iframes, no scripts.
 *   - 'media':  strict + img, video, audio, source, table, tr, td, th, thead,
 *     tbody, div, pre. For torrent media-info / BD-info blocks.
 *   - 'comment': strict + img (with src filtering). For user comments.
 */
final class SafeHtml
{
    /** @var array<string, HtmlSanitizer> */
    private static array $cache = [];

    /**
     * Sanitize HTML for safe output.
     *
     * @param  string  $html  Raw HTML to sanitize
     * @param  string  $preset  'strict' | 'media' | 'comment'
     */
    public static function sanitize(string $html, string $preset = 'strict'): string
    {
        if ($html === '') {
            return '';
        }

        return self::sanitizer($preset)->sanitize($html);
    }

    /**
     * Sanitize and return a Value Object for type safety.
     */
    public static function make(string $html, string $preset = 'strict'): SanitizedHtml
    {
        return new SanitizedHtml(self::sanitize($html, $preset));
    }

    private static function sanitizer(string $preset): HtmlSanitizer
    {
        if (isset(self::$cache[$preset])) {
            return self::$cache[$preset];
        }

        $config = match ($preset) {
            'media' => self::mediaConfig(),
            'comment' => self::commentConfig(),
            default => self::strictConfig(),
        };

        return self::$cache[$preset] = new HtmlSanitizer($config);
    }

    private static function strictConfig(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig())
            ->forceHttpsUrls()
            ->allowRelativeLinks()
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowElement('a', ['href', 'title', 'class'])
            ->allowElement('b')
            ->allowElement('i')
            ->allowElement('em')
            ->allowElement('strong')
            ->allowElement('code')
            ->allowElement('pre', ['class'])
            ->allowElement('br')
            ->allowElement('span', ['class'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('p', ['class']);
    }

    private static function commentConfig(): HtmlSanitizerConfig
    {
        return self::strictConfig()
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('blockquote', ['cite'])
            ->allowMediaSchemes(['https', 'http']);
    }

    private static function mediaConfig(): HtmlSanitizerConfig
    {
        return self::strictConfig()
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('video', ['src', 'width', 'height', 'controls'])
            ->allowElement('audio', ['src', 'controls'])
            ->allowElement('source', ['src'])
            ->allowElement('table', ['class', 'style'])
            ->allowElement('tr', ['class', 'style'])
            ->allowElement('td', ['colspan', 'rowspan', 'class', 'style', 'align', 'valign'])
            ->allowElement('th', ['colspan', 'rowspan', 'class', 'style', 'align', 'valign'])
            ->allowElement('thead', ['class'])
            ->allowElement('tbody', ['class'])
            ->allowElement('div', ['class', 'style'])
            ->allowElement('pre', ['class', 'style'])
            ->allowMediaSchemes(['https', 'http']);
    }
}
