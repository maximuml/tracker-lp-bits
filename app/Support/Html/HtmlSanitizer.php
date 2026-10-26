<?php

declare(strict_types=1);

namespace App\Support\Html;

use Dom\CDATASection;
use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\ProcessingInstruction;

/**
 * HTML sanitizer with whitelist-based tag/attribute/URL filtering.
 *
 * Strips dangerous HTML tags, attributes, and URL schemes while
 * preserving safe formatting tags. Used by SafeHtml::fromUntrustedHtml()
 * to sanitize user-controlled HTML before rendering.
 *
 * The whitelist approach is more secure than blocklist-based filtering
 * because new/unknown tags are automatically stripped.
 *
 * Sanitization runs on the real DOM tree produced by the HTML5 parser
 * (\Dom\HTMLDocument), not on the raw byte stream, so markup-obfuscation
 * bypasses (recombined fragments like <<script>script>, stray closing
 * tags like </script >, unquoted attributes, comments, CDATA) can never
 * smuggle a live element past the filter — anything the parser turns
 * into a node is filtered as a node.
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
     * Elements removed together with their whole subtree — active content,
     * raw-text containers (whose text could re-tokenize as markup), foreign
     * content, and interactive/form elements. Unknown-but-inert elements
     * (e.g. <marquee>) are only unwrapped, keeping their text children.
     *
     * @var array<string, bool>
     */
    private const DROP_WITH_CONTENT = [
        'script' => true, 'style' => true, 'iframe' => true,
        'object' => true, 'embed' => true, 'applet' => true,
        'form' => true, 'input' => true, 'button' => true,
        'select' => true, 'option' => true, 'optgroup' => true,
        'meta' => true, 'link' => true, 'base' => true, 'title' => true,
        'svg' => true, 'math' => true, 'template' => true,
        'textarea' => true, 'xmp' => true, 'plaintext' => true,
        'listing' => true, 'frame' => true, 'frameset' => true,
        'audio' => true, 'video' => true, 'source' => true,
        'track' => true, 'map' => true, 'area' => true, 'param' => true,
        'picture' => true, 'noscript' => true,
    ];

    /**
     * Sanitize an HTML string, removing dangerous tags, attributes, and URLs.
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        try {
            $doc = HTMLDocument::createFromString(
                $html,
                LIBXML_NOERROR | LIBXML_COMPACT,
            );
        } catch (\Throwable) {
            // Parser failure: degrade to escaped plain text, never raw HTML.
            return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body instanceof Element) {
            return '';
        }

        // Iterative traversal — recursion could blow the stack on deeply
        // nested hostile markup. Only <body> children are visited and
        // serialized, so parser-level context tags (<html>, <body onload=…>,
        // <head>, <!doctype>) never reach the output.
        /** @var list<Node> $pending */
        $pending = [];
        foreach ($body->childNodes as $child) {
            $pending[] = $child;
        }

        while ($pending !== []) {
            $node = array_pop($pending);

            if ($node instanceof Comment
                || $node instanceof ProcessingInstruction
                || $node instanceof CDATASection) {
                $node->remove();

                continue;
            }

            if (! $node instanceof Element) {
                continue; // text — serialized escaped by saveHtml()
            }

            $tag = strtolower($node->localName);

            if (! isset(self::ALLOWED_TAGS[$tag])) {
                if (isset(self::DROP_WITH_CONTENT[$tag])) {
                    $node->remove();
                } else {
                    // Unwrap unknown-but-inert elements: hoist children into
                    // the parent and keep them pending for filtering too.
                    foreach ($node->childNodes as $child) {
                        $pending[] = $child;
                    }
                    $parent = $node->parentNode;
                    while ($node->firstChild !== null) {
                        $parent?->insertBefore($node->firstChild, $node);
                    }
                    $node->remove();
                }

                continue;
            }

            self::filterElementAttributes($node, $tag);

            foreach ($node->childNodes as $child) {
                $pending[] = $child;
            }
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $doc->saveHtml($child);
        }

        return $result;
    }

    /**
     * Remove every attribute that is not on the tag's whitelist, and any
     * whitelisted attribute whose value fails the URL/CSS checks.
     */
    private static function filterElementAttributes(Element $element, string $tag): void
    {
        $allowedAttrs = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach ($element->getAttributeNames() as $name) {
            $lower = strtolower($name);
            $value = (string) $element->getAttribute($name);

            // Event handlers are never whitelisted; the str_starts_with
            // guard is a second layer in case one ever were added.
            if (! isset($allowedAttrs[$lower]) || str_starts_with($lower, 'on')) {
                $element->removeAttribute($name);

                continue;
            }

            if (($lower === 'href' || $lower === 'src') && ! self::isSafeUrl($value)) {
                $element->removeAttribute($name);

                continue;
            }

            if ($lower === 'style') {
                $filtered = self::filterStyle($value);
                if ($filtered === '') {
                    $element->removeAttribute($name);
                } else {
                    $element->setAttribute($name, $filtered);
                }
            }
        }
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

        // U+FFFD in a URL means the parser replaced a raw NUL or malformed
        // byte — never legitimate in an href/src, and rejecting it keeps
        // java\x00script:-style payloads out even after normalization.
        if (str_contains($url, "\xEF\xBF\xBD")) {
            return false;
        }

        // Parse scheme — any URL with a scheme must use an allowed one.
        // Normalize first: browsers strip C0 controls/whitespace inside
        // URLs (java\tscript:) and decode entities before URL parsing, so
        // parse_url() must be given the same normalized form.
        $normalized = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = (string) preg_replace('/[\x00-\x20]/', '', $normalized);
        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

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
        // Strip CSS comments up front — otherwise ex/**/pression() and
        // u/**/rl() sail past the substring checks below.
        $style = (string) preg_replace('#/\*.*?\*/#s', '', $style);

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

            // A backslash can encode any character as a CSS escape
            // (e.g. \65 xpression) — none of the whitelisted properties
            // legitimately need escapes, so reject the whole declaration.
            if (str_contains($value, '\\')) {
                continue;
            }

            $squashed = strtolower((string) preg_replace('/\s+/', '', $value));
            foreach (['expression(', 'url(', 'javascript:', 'vbscript:', 'behavior:', 'binding', '@import', '@charset'] as $bad) {
                if (str_contains($squashed, $bad)) {
                    continue 2;
                }
            }

            $safe[] = $property.': '.$value;
        }

        return implode('; ', $safe);
    }
}
