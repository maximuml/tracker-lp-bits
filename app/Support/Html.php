<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\Escape;
use App\Support\Html\SafeHtml;
use App\Support\Html\Tag;

/**
 * Stateless HTML-string emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration).
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state.
 *
 * W2-02: Decomposed into {@see Escape} and {@see Tag}.
 * This class remains as a facade for backward compatibility.
 */
final class Html
{
    // ── Escape ──────────────────────────────────────────────────────

    public static function cleanListChildren(string $html): string
    {
        return Escape::cleanListChildren($html);
    }

    // ── Tag ─────────────────────────────────────────────────────────

    public static function keyShortcutScript(int $page = 1, int $pages = 1, string $nonce = ''): SafeHtml
    {
        return Tag::keyShortcutScript($page, $pages, $nonce);
    }

    /**
     * @param  array<string, string>  $labels
     */
    public static function promotionSelectOptions(int $selected, int $hide, array $labels): SafeHtml
    {
        return Tag::promotionSelectOptions($selected, $hide, $labels);
    }

    public static function promotionSelection(int $selected = 0, int $hide = 0): SafeHtml
    {
        return Tag::promotionSelection($selected, $hide);
    }

    public static function settingsRow(
        string $head,
        string|SafeHtml $follow,
        bool $escape = true,
        string $relation = '',
    ): SafeHtml {
        return Tag::settingsRow($head, $follow, $escape, $relation);
    }

    /**
     * @param  iterable<array{id?: mixed, content?: mixed}>  $items
     */
    public static function tooltipContainer(iterable $items, int $width = 400): string
    {
        return Tag::tooltipContainer($items, $width);
    }

    public static function quickReply(string $formName, string $textareaName, string $submitLabel): SafeHtml
    {
        return Tag::quickReply($formName, $textareaName, $submitLabel);
    }

    public static function formatUrl(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        return Tag::formatUrl($url, $newWindow, $text, $linkClass);
    }

    public static function formatImg(string $src, bool $enableResizer, int $maxWidth, int $maxHeight, string $imgId = ''): string
    {
        return Tag::formatImg($src, $enableResizer, $maxWidth, $maxHeight, $imgId);
    }

    public static function formatYoutube(string $src, int|string $width = '', int|string $height = ''): string
    {
        return Tag::formatYoutube($src, $width, $height);
    }

    public static function formatSpoiler(string $content, string $title = '', bool $defaultCollapsed = true): string
    {
        return Tag::formatSpoiler($content, $title, $defaultCollapsed);
    }

    public static function formatHidden(string $content): string
    {
        return Tag::formatHidden($content);
    }

    public static function formatTextAlign(string $text, string $align): string
    {
        return Tag::formatTextAlign($text, $align);
    }

    // ── Layout (delegates to PageRenderer) ──────────────────────────

    /**
     * Render the page footer. Backs the legacy `stdfoot()` helper.
     */
    public static function stdfoot(): void
    {
        app(PageRenderer::class)->footer();
    }
}
