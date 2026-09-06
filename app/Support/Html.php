<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\Escape;
use App\Support\Html\Message;
use App\Support\Html\Tag;

/**
 * Stateless HTML-string emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
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

    public static function escapeAttr(string $value): string
    {
        return Escape::escapeAttr($value);
    }

    public static function cleanListChildren(string $html): string
    {
        return Escape::cleanListChildren($html);
    }

    // ── Tag ─────────────────────────────────────────────────────────

    public static function tableRow(string $class, string ...$cells): string
    {
        return Tag::tableRow($class, ...$cells);
    }

    public static function keyShortcutScript(int $page = 1, int $pages = 1): string
    {
        return Tag::keyShortcutScript($page, $pages);
    }

    /**
     * @param  array<string, string>  $labels
     */
    public static function promotionSelectOptions(int $selected, int $hide, array $labels): string
    {
        return Tag::promotionSelectOptions($selected, $hide, $labels);
    }

    /**
     * @param  iterable<array{id?: mixed, name?: mixed}>  $items
     */
    public static function torrentSelect(
        string $name,
        string $selectName,
        string $chooseOneLabel,
        int $selectedId,
        iterable $items,
    ): string {
        return Tag::torrentSelect($name, $selectName, $chooseOneLabel, $selectedId, $items);
    }

    public static function torrentSelection(string $name, string $selName, string $listName, int $selectedId = 0, int $mode = 0): string
    {
        return Tag::torrentSelection($name, $selName, $listName, $selectedId, $mode);
    }

    public static function promotionSelection(int $selected = 0, int $hide = 0): string
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

    public static function settingsRowSmall(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        return Tag::settingsRowSmall($head, $follow, $escape, $relation);
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

    public static function emitSettingsRowSmall(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        return Tag::emitSettingsRowSmall($head, $follow, $escape, $relation, $return);
    }

    public static function settingsCells(string $head, string $follow): string
    {
        return Tag::settingsCells($head, $follow);
    }

    /**
     * @param  iterable<array{id?: mixed, content?: mixed}>  $items
     */
    public static function tooltipContainer(iterable $items, int $width = 400): string
    {
        return Tag::tooltipContainer($items, $width);
    }

    /**
     * @param  array<array-key, string>  $header
     * @param  iterable<array<array-key, mixed>>  $rows
     * @param  array<string, mixed>  $options
     */
    public static function buildTable(array $header, iterable $rows, array $options = []): string
    {
        return Tag::buildTable($header, $rows, $options);
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

    public static function formatFlash(string $src, int|string $width, int|string $height): string
    {
        return Tag::formatFlash($src, $width, $height);
    }

    public static function formatFlv(string $src, int|string $width, int|string $height): string
    {
        return Tag::formatFlv($src, $width, $height);
    }

    public static function formatYoutube(string $src, int|string $width = '', int|string $height = ''): string
    {
        return Tag::formatYoutube($src, $width, $height);
    }

    public static function formatVideo(string $src, int|string $width, int|string $height): string
    {
        return Tag::formatVideo($src, $width, $height);
    }

    public static function formatAudio(string $src): string
    {
        return Tag::formatAudio($src);
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

    public static function messageAlertVoid(string $url, string $text, string $bgcolor = 'red'): void
    {
        Message::messageAlertVoid($url, $text, $bgcolor);
    }

    public static function stdMessage(string $heading, string $text, bool $htmlstrip = false): void
    {
        Message::stdMessage($heading, $text, $htmlstrip);
    }

    public static function quickReplyVoid(string $formName, string $textareaName, string $submitLabel): void
    {
        Message::quickReplyVoid($formName, $textareaName, $submitLabel);
    }

    public static function smiliesFrame(): void
    {
        Message::smiliesFrame();
    }

    // ── Layout (delegates to PageLayout/Frame) ──────────────────────

    /**
     * Render the page header. Backs the legacy `stdhead()` helper.
     */
    public static function stdhead(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): void
    {
        $context = PageLayoutContext::fromSupportContext();
        PageLayout::setContext($context);
        PageLayout::header($title, $msgalert, $script, $place);
    }

    /**
     * Render the page footer. Backs the legacy `stdfoot()` helper.
     */
    public static function stdfoot(): void
    {
        PageLayout::footer();
    }

    /**
     * Open a framed content block. Backs the legacy `begin_frame()` helper.
     */
    public static function beginFrame(string $caption = '', bool $center = false, int $padding = 10, string $width = '100%', string $caption_center = 'left'): void
    {
        echo Frame::open($caption, $center, $padding, $width, $caption_center);
    }

    /**
     * Close a framed content block. Backs the legacy `end_frame()` helper.
     */
    public static function endFrame(): void
    {
        echo Frame::CLOSE;
    }

    /**
     * Open a data table. Backs the legacy `begin_table()` helper.
     */
    public static function beginTable(bool $fullwidth = false, int $padding = 5): void
    {
        echo Frame::tableOpen($fullwidth, $padding);
    }

    /**
     * Close a data table. Backs the legacy `end_table()` helper.
     */
    public static function endTable(): void
    {
        echo Frame::TABLE_CLOSE;
    }

    /**
     * Emit a settings table row. Backs the legacy `tr()` helper.
     */
    public static function tr(mixed $x, mixed $y, bool|int $noesc = false, string $relation = '', bool $return = false): ?string
    {
        return self::emitSettingsRow((string) $x, (string) $y, ! (bool) $noesc, $relation, $return);
    }

    /**
     * Emit a narrow-label settings row. Backs the legacy `tr_small()` helper.
     *
     * The legacy `$noesc` flag is inverted before passing to the escaping
     * helper, matching the original semantics.
     */
    public static function trSmall(
        mixed $x,
        mixed $y,
        bool|int $noesc = false,
        string $relation = '',
        bool $return = false,
    ): ?string {
        return self::emitSettingsRowSmall((string) $x, (string) $y, ! (bool) $noesc, $relation, $return);
    }

    /**
     * Emit two bare table cells. Backs the legacy `twotd()` helper.
     */
    public static function twoTd(string $head, string $follow): void
    {
        echo self::settingsCells($head, $follow);
    }
}
