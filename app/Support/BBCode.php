<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\SafeHtml;

/**
 * Stateless HTML builders for BBCode tags, extracted from
 * `include/functions.php`.
 *
 * Phase 5 of the legacy migration.
 * The legacy procedural helpers
 *
 *   - `formatUrl()`        (`<a>` tag with optional class and `target=_blank`)
 *   - `formatAdUrl()`      (ad-redirect URL — delegates to `url()`)
 *   - `formatCode()`       (`[code]` block with `<pre><code>` body)
 *   - `formatImg()`        (`<img>` with optional resizer onload hook)
 *   - `formatYoutube()`    (`<iframe>` YouTube embed)
 *   - `formatSpoiler()`    (`<details>`/`<summary>` collapsible)
 *   - `formatHidden()`     (`<span class="hidden-text">` wrapper)
 *   - `formatTextAlign()`  (`<div class="nx-align-…">`)
 *
 * all collapse into the static methods below. Each returns **bare**
 * HTML — the legacy proxies in `include/functions.php` are
 * responsible for the `addTempCode()` placeholder dance (and for
 * `filter_src()` URL sanitisation on the embed methods).
 *
 * Keeping `addTempCode()` and `filter_src()` in the proxy layer
 * means this class stays pure: no DI, no DB, no global state. Same
 * convention as {@see Ratio}, {@see Validators},
 * {@see Format}, {@see Strings}, {@see Time}, {@see Codec}.
 */
final class BBCode
{
    /**
     * Render an `<a href="…">…</a>` link.
     *
     * Mirrors the legacy `formatUrl()` exactly:
     *   - empty `$text` falls back to the URL itself
     *   - `$newWindow === true` (loose) appends `target="_blank"`;
     *     anything else (false, "0", null) does NOT
     *   - `$linkClass`, if non-empty, becomes `class="…"`
     *
     * The `$url` is sanitised: only `http`, `https`, `mailto`, and
     * relative URLs are allowed. Dangerous schemes (`javascript:`,
     * `data:`, `vbscript:`, etc.) are neutralised. The URL and link
     * text are HTML-escaped for attribute context.
     */
    public static function url(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        // Decode HTML entities first — Comment::format() runs
        // htmlspecialchars() on the whole text before parsing BBCode,
        // so the URL arrives entity-encoded (e.g. &#x6a;avascript:).
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // \x08-delimited tempCode placeholders must never survive into an
        // attribute value: resolveTempCodes() substitutes them blindly,
        // including inside href="…", and the stored markup would break
        // out of the quotes. Stripping the byte defuses the placeholder.
        $decoded = str_replace("\x08", '', $decoded);

        // Validate the URL scheme — only allow safe schemes or
        // relative URLs (no scheme). Reject javascript:, data:, etc.
        $safeUrl = self::sanitizeUrl($decoded);
        if ($safeUrl === null) {
            // Dangerous scheme — render as plain text, no link.
            $displayText = $text !== '' ? $text : $url;

            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8', false);
        }

        if (! $text) {
            $text = $url;
        }

        // Escape link text for the text context; it arrives
        // entity-encoded from Comment::format(), so double-encode
        // must stay off and the value goes through the SafeHtml
        // boundary. URL and class are plain strings — `{{ }}` escapes.
        $escapedText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);

        return trim(view('components.support.url-link', [
            'linkClass' => $linkClass,
            'url' => $safeUrl,
            'newWindow' => $newWindow,
            'text' => SafeHtml::fromTrustedHtml($escapedText),
        ])->render());
    }

    /**
     * Validate a URL and return a safe version, or null if dangerous.
     *
     * Allows:
     * - Relative URLs (no scheme): `/path`, `page.php`, `?foo=bar`
     * - http://, https://, mailto:, ftp://
     *
     * Rejects:
     * - javascript:, data:, vbscript:, file:, blob:, etc.
     * - Any scheme not in the allowlist
     *
     * @param  string  $url  The raw (entity-decoded) URL to validate.
     * @return string|null The safe URL, or null if the scheme is dangerous.
     */
    public static function sanitizeUrl(string $url): ?string
    {
        $trimmed = trim($url);

        // Empty URL — safe, render as empty link.
        if ($trimmed === '') {
            return '';
        }

        // Check for a scheme (scheme:...). Relative URLs have no scheme.
        // Use a case-insensitive match for scheme:// or scheme: prefix.
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/i', $trimmed, $matches)) {
            $scheme = strtolower($matches[1]);
            $allowed = ['http', 'https', 'mailto', 'ftp'];

            if (! in_array($scheme, $allowed, true)) {
                // Dangerous scheme (javascript:, data:, vbscript:, etc.)
                return null;
            }
        }

        // Also check for scheme-less but dangerous patterns like
        // "//evil.com" (protocol-relative) — allow but they're fine.
        // And "&#x6a;avascript:" that decoded to "javascript:" is
        // already caught above.

        return $trimmed;
    }

    /**
     * Turn plain URLs in text into clickable links.
     *
     * Mirrors the legacy `format_urls()` regex and delegates each
     * matched URL to {@see url} wrapped with a temp-code placeholder.
     */
    public static function formatUrls(string $text, bool $newWindow = false): string
    {
        return (string) preg_replace_callback(
            "/((https?|ftp|gopher|news|telnet|mms|rtsp):\/\/[^()\[\]<>\s]+)/i",
            function (array $matches) use ($newWindow): string {
                return Comment::addTempCode(self::url($matches[1], $newWindow, '', 'faqlink'));
            },
            $text,
        );
    }

    /**
     * Render a `[code]` block. Used for verbatim quoting of source
     * code in forum posts and torrent descriptions.
     *
     * The `$label` is the language-aware "Code" string — the legacy
     * proxy threads `nexus_trans('label.text_code')` through here.
     * Pinning the label as a parameter keeps the class free of
     * Laravel-specific helpers.
     */
    public static function code(string $text, string $label): string
    {
        return trim(view('components.support.code-block', [
            'label' => $label,
            'text' => SafeHtml::fromTrustedHtml($text),
        ])->render());
    }

    /**
     * Render an inline `<img>`. The `$src` is interpolated verbatim
     * — the legacy proxy is expected to have passed it through
     * `filter_src()` first, which short-circuits on cross-host,
     * non-image-extension, or non-existent paths.
     *
     * Empty `$src` short-circuits to an empty string (the legacy
     * `formatImg()` does the same after `filter_src()`).
     *
     * When `$enableResizer` is true the image gets `data-scale` (delegated
     * load handler in common.js calls `Scale()`) plus `data-zoomable` for
     * the nx-zoom lightbox. The error fallback (`data-img-fallback`) is
     * always emitted regardless of resizer flag.
     */
    public static function img(string $src, bool $enableResizer, int $maxWidth, int $maxHeight, string $imgId = ''): string
    {
        if (empty($src)) {
            return '';
        }

        return trim(view('components.support.bbcode-img', [
            'imgId' => $imgId,
            'src' => $src,
            'enableResizer' => $enableResizer,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
        ])->render());
    }

    /**
     * Render a YouTube `<iframe>` embed. Defaults to 560×315.
     *
     * The `$src` URL is parsed for its `v=` query parameter; if
     * none is found, the video-id is empty (which produces a
     * broken iframe — preserved verbatim from the legacy
     * `formatYoutube()` to avoid surprising existing call sites
     * that may rely on the broken-iframe sentinel).
     */
    public static function youtube(string $src, int|string $width = 0, int|string $height = 0): string
    {
        if (empty($src)) {
            return '';
        }
        if (! $width) {
            $width = 560;
        }
        if (! $height) {
            $height = 315;
        }
        $queryString = parse_url($src, PHP_URL_QUERY);
        $parameters = [];
        if (is_string($queryString)) {
            parse_str($queryString, $parameters);
        }
        $videoIdValue = $parameters['v'] ?? '';
        $videoId = is_scalar($videoIdValue) ? (string) $videoIdValue : '';
        // parse_str() urldecodes the query — a percent-encoded quote (%22)
        // would otherwise arrive as a literal " and break out of the src
        // attribute. YouTube ids are [A-Za-z0-9_-]; strip everything else.
        $videoId = (string) preg_replace('/[^A-Za-z0-9_-]/', '', $videoId);

        return trim(view('components.support.yt-iframe', [
            'width' => $width,
            'height' => $height,
            'videoId' => $videoId,
        ])->render());
    }

    /**
     * Render a `<span class="hidden-text">…</span>` wrapper.
     * Thin delegation to {@see Strings::hidden} — kept on the
     * BBCode surface for symmetry with the other `[…]` tag helpers.
     */
    public static function hidden(string $content): SafeHtml
    {
        return Strings::hidden($content);
    }

    /**
     * Render a `<details>`/`<summary>` spoiler block.
     *
     * The `$defaultTitle` is the language-aware default — the
     * legacy proxy threads `$lang_functions['spoiler_default_title']`
     * through here. The empty-string check is loose: `'0'` is
     * considered empty by the legacy contract (matching the
     * `!$title` test) and falls through to the default.
     */
    public static function spoiler(string $content, string $title, string $defaultTitle, bool $defaultCollapsed = true): string
    {
        if (! $title) {
            $title = $defaultTitle;
        }
        // See url() — a tempCode placeholder smuggled into the title would
        // resolve a foreign element inside <summary>.
        $title = str_replace("\x08", '', $title);

        return trim(view('components.support.spoiler-details', [
            'openAttr' => $defaultCollapsed ? '' : ' open',
            'title' => SafeHtml::fromTrustedHtml($title),
            'content' => SafeHtml::fromTrustedHtml($content),
        ])->render());
    }

    /**
     * Render a `<div class="nx-align-…">…</div>`. The `$align`
     * value (`left`, `center`, `right`, `justify`) is interpolated
     * verbatim — the legacy proxy is called only from the BBCode
     * parser with a hard-coded set of values, so no validation is
     * performed here.
     */
    public static function textAlign(string $text, string $align): string
    {
        return trim(view('components.support.align-div', [
            'align' => $align,
            'text' => SafeHtml::fromTrustedHtml($text),
        ])->render());
    }

    /**
     * Rewrite `[quote]` / `[quote=author]` blocks as
     * `<fieldset><legend>` HTML. Mismatched or out-of-order tag pairs
     * cause the input to be returned verbatim (the legacy contract —
     * unmatched bbcode is shown raw to the user).
     *
     * The `$quoteLabel` is the localized "Quote" string the legacy
     * proxy resolves via `nexus_trans("label.text_quote")` and threads
     * through to this helper.
     */
    public static function quotes(string $text, string $quoteLabel): string
    {
        preg_match_all('/\[quote.*?\]/i', $text, $result, PREG_PATTERN_ORDER);
        $openTags = $result[0];
        preg_match_all('/\[\/quote\]/i', $text, $result, PREG_PATTERN_ORDER);
        $closeTags = $result[0];
        if (count($openTags) !== count($closeTags)) {
            return $text;
        }

        $openPositions = [];
        $pos = -1;
        foreach ($openTags as $needle) {
            $openPositions[] = $pos = strpos($text, $needle, $pos + 1);
        }
        $closePositions = [];
        $pos = -1;
        foreach ($closeTags as $needle) {
            $closePositions[] = $pos = strpos($text, $needle, $pos + 1);
        }
        for ($i = 0, $n = count($openPositions); $i < $n; $i++) {
            if ($openPositions[$i] > $closePositions[$i]) {
                return $text;
            }
        }

        $open = trim(view('components.support.quote-open', ['label' => $quoteLabel])->render());
        $cite = trim(view('components.support.quote-cite', [
            'label' => $quoteLabel,
            'cite' => SafeHtml::fromPlainText('\\1'),
        ])->render());
        $close = trim(view('components.support.quote-close')->render());
        $text = (string) preg_replace('/\[quote\]/i', $open, $text);
        $text = (string) preg_replace('/\[quote=(.+?)\]/i', $cite, $text);
        $text = (string) preg_replace('/\[\/quote\]/i', $close, $text);

        return $text;
    }
}
