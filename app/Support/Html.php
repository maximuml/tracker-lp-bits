<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\Escape;
use App\Support\Html\Message;
use App\Support\Html\SafeHtml;
use App\Support\Html\Tag;

/**
 * Stateless HTML-string emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration).
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state.
 *
 * W2-02: Decomposed into {@see Escape}, {@see Tag}, and {@see Message}.
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

    public static function keyShortcutScript(int $page = 1, int $pages = 1, string $nonce = ''): string
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
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        return Tag::settingsRow($head, $follow, $escape, $relation);
    }

    public static function emitSettingsRow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        return Tag::emitSettingsRow($head, $follow, $escape, $relation, $return);
    }

    public static function emitSettingsFrow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        return Tag::emitSettingsFrow($head, $follow, $escape, $relation, $return);
    }

    /**
     * @param  iterable<array{id?: mixed, content?: mixed}>  $items
     */
    public static function tooltipContainer(iterable $items, int $width = 400): string
    {
        return Tag::tooltipContainer($items, $width);
    }

    public static function quickReply(string $formName, string $textareaName, string $submitLabel): string
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

    // ── Message ─────────────────────────────────────────────────────

    public static function messageAlert(string $url, string $text, string $bgcolor = 'red'): string
    {
        return Message::messageAlert($url, $text, $bgcolor);
    }

    // ── Layout (delegates to PageRenderer) ──────────────────────────

    /**
     * Render the page footer. Backs the legacy `stdfoot()` helper.
     */
    public static function stdfoot(): void
    {
        app(PageRenderer::class)->footer();
    }

    /**
     * Emit a settings table row. Backs the legacy `tr()` helper.
     */
    public static function tr(mixed $x, mixed $y, bool|int $noesc = false, string $relation = '', bool $return = false): ?string
    {
        return self::emitSettingsRow((string) $x, (string) $y, ! (bool) $noesc, $relation, $return);
    }

    /**
     * Emit a grid-mode settings row (`nx-fhead`/`nx-fcell` divs) for
     * `.nx-fgrid` hosts. Mirrors {@see tr()}: `$noesc` is inverted
     * before passing to the escaping helper.
     */
    public static function frow(mixed $x, mixed $y, bool|int $noesc = false, string $relation = '', bool $return = false): ?string
    {
        return self::emitSettingsFrow((string) $x, (string) $y, ! (bool) $noesc, $relation, $return);
    }
}
