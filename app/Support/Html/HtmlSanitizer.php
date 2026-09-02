<?php

declare(strict_types=1);

namespace App\Support\Html;

/**
 * HTML sanitizer with whitelist-based tag/attribute/URL filtering.
 *
 * Strips dangerous HTML tags, attributes, and URL schemes while
 * preserving safe formatting tags. Used by SafeHtml::fromUntrustedHtml()
 * to sanitize user-controlled HTML before rendering.
 *
 * The whitelist approach is more secure than blocklist-based filtering
 * because new/unknown tags are automatically stripped.
 */
final class HtmlSanitizer
{
    /**
     * Tags that are allowed in sanitized HTML.
     *
     * @var array<string, bool>
     */
    private const ALLOWED_TAGS = [
        'p' => true, 'br' => true, 'hr' => true,
        'b' => true, 'strong' => true,
        'i' => true, 'em' => true,
        'u' => true, 's' => true, 'del' => true, 'ins' => true,
        'ul' => true, 'ol' => true, 'li' => true,
        'blockquote' => true, 'pre' => true, 'code' => true,
        'span' => true, 'font' => true,
        'div' => true,
        'table' => true, 'thead' => true, 'tbody' => true,
        'tr' => true, 'td' => true, 'th' => true,
        'a' => true, 'img' => true,
        'h1' => true, 'h2' => true, 'h3' => true,
        'h4' => true, 'h5' => true, 'h6' => true,
        'sub' => true, 'sup' => true,
        'small' => true, 'big' => true,
        'center' => true,
    ];

    /**
     * Attributes allowed on specific tags.
     *
     * @var array<string, array<string, bool>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href' => true, 'title' => true, 'target' => true, 'class' => true],
        'img' => ['src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true, 'class' => true],
        'span' => ['style' => true, 'class' => true],
        'div' => ['style' => true, 'class' => true],
        'font' => ['color' => true, 'size' => true, 'face' => true],
        'p' => ['style' => true, 'class' => true, 'align' => true],
        'table' => ['border' => true, 'cellspacing' => true, 'cellpadding' => true, 'width' => true, 'class' => true],
        'td' => ['class' => true, 'align' => true, 'valign' => true, 'width' => true, 'colspan' => true, 'rowspan' => true],
        'th' => ['class' => true, 'align' => true, 'valign' => true, 'width' => true, 'colspan' => true, 'rowspan' => true],
        'tr' => ['class' => true],
        'h1' => ['class' => true], 'h2' => ['class' => true], 'h3' => ['class' => true],
        'h4' => ['class' => true], 'h5' => ['class' => true], 'h6' => ['class' => true],
        'blockquote' => ['class' => true],
        'pre' => ['class' => true],
        'code' => ['class' => true],
        'ol' => ['class' => true, 'start' => true],
        'ul' => ['class' => true],
        'li' => ['class' => true],
    ];

    /**
     * URL schemes that are allowed in href/src attributes.
     *
     * @var array<string, bool>
     */
    private const ALLOWED_URL_SCHEMES = [
        'http' => true, 'https' => true, 'mailto' => true, 'ftp' => true,
    ];

    /**
     * CSS properties that are allowed in style attributes.
     *
     * @var array<string, bool>
     */
    private const ALLOWED_CSS_PROPERTIES = [
        'color' => true, 'background-color' => true, 'font-size' => true,
        'font-family' => true, 'font-weight' => true, 'font-style' => true,
        'text-align' => true, 'text-decoration' => true,
        'word-break' => true, 'word-wrap' => true,
        'margin' => true, 'margin-left' => true, 'margin-right' => true,
        'padding' => true, 'padding-left' => true, 'padding-right' => true,
        'border' => true, 'width' => true, 'height' => true,
        'display' => true,
    ];

    /**
     * Sanitize an HTML string, removing dangerous tags, attributes, and URLs.
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove HTML comments (can hide conditional IE XSS payloads)
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);

        // Parse and filter tags
        $html = (string) preg_replace_callback(
            '/<(\w+)([^>]*)>/s',
            static fn (array $m): string => self::filterTag((string) $m[1], (string) $m[2]),
            $html
        );

        // Filter closing tags
        $html = (string) preg_replace_callback(
            '/<\/(\w+)>/s',
            static fn (array $m): string => self::ALLOWED_TAGS[strtolower((string) $m[1])] ?? ''
                ? '</'.strtolower((string) $m[1]).'>'
                : '',
            $html
        );

        return $html;
    }

    /**
     * Filter an opening tag: keep it only if the tag is allowed
     * and all its attributes are safe.
     */
    private static function filterTag(string $tag, string $attrString): string
    {
        $tag = strtolower($tag);

        if (! isset(self::ALLOWED_TAGS[$tag])) {
            return '';
        }

        $allowedAttrs = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $safeAttrs = self::filterAttributes($attrString, $allowedAttrs, $tag);

        return $safeAttrs !== '' ? '<'.$tag.' '.$safeAttrs.'>' : '<'.$tag.'>';
    }

    /**
     * Filter attributes on a tag, keeping only allowed ones.
     */
    /**
     * @param  array<string, bool>  $allowedAttrs
     */
    private static function filterAttributes(string $attrString, array $allowedAttrs, string $tag): string
    {
        if ($attrString === '' || $allowedAttrs === []) {
            return '';
        }

        // Parse attributes
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $attrString, $matches, PREG_SET_ORDER);
        preg_match_all("/(\w+)\s*=\s*'([^']*)'/", $attrString, $singleMatches, PREG_SET_ORDER);

        $allAttrs = array_merge($matches, $singleMatches);
        $safeAttrs = [];

        foreach ($allAttrs as $attr) {
            $name = strtolower((string) $attr[1]);
            $value = (string) $attr[2];

            if (! isset($allowedAttrs[$name])) {
                continue;
            }

            // Check for event handler attributes (on*)
            if (str_starts_with($name, 'on')) {
                continue;
            }

            // Check URL attributes for dangerous schemes
            if (($name === 'href' || $name === 'src') && ! self::isSafeUrl($value)) {
                continue;
            }

            // Check style attribute for dangerous CSS
            if ($name === 'style') {
                $value = self::filterStyle($value);
                if ($value === '') {
                    continue;
                }
            }

            $safeAttrs[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return implode(' ', $safeAttrs);
    }

    /**
     * Check if a URL is safe (uses an allowed scheme and no javascript:).
     */
    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        // Block empty URLs
        if ($url === '') {
            return false;
        }

        // Check for protocol-relative URLs (//evil.com)
        if (str_starts_with($url, '//')) {
            return true; // allowed — same scheme as page
        }

        // Parse scheme — any URL with a scheme must use an allowed one
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== '') {
            // Block dangerous schemes explicitly
            if (in_array($scheme, ['javascript', 'data', 'vbscript', 'file'], true)) {
                return false;
            }

            return isset(self::ALLOWED_URL_SCHEMES[$scheme]);
        }

        // No scheme — relative URL (e.g. /page, #anchor, ?query) — safe
        return true;
    }

    /**
     * Filter CSS in a style attribute, keeping only safe properties.
     */
    private static function filterStyle(string $style): string
    {
        $declarations = explode(';', $style);
        $safe = [];

        foreach ($declarations as $decl) {
            $decl = trim($decl);
            if ($decl === '') {
                continue;
            }

            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (! isset(self::ALLOWED_CSS_PROPERTIES[$property])) {
                continue;
            }

            // Block expression() and url() with javascript:
            if (preg_match('/expression\s*\(/i', $value)) {
                continue;
            }
            if (preg_match('/url\s*\(\s*["\']?\s*javascript:/i', $value)) {
                continue;
            }

            $safe[] = $property.': '.$value;
        }

        return implode('; ', $safe);
    }
}
