<?php

declare(strict_types=1);

namespace App\Support\Html;

use App\Support\BBCode;
use App\Support\Comment;
use App\Support\Security;
use App\Support\Smilies;

/**
 * HTML tag builders extracted from the legacy Html facade.
 *
 * Pure string-building functions — no DI, no DB, no config, no global state
 * (except where legacy helpers like SearchBox/Language are invoked).
 */
final class Tag
{
    /**
     * Emit the `<script>` block that legacy `key_shortcut()` injects
     * into paginated views to expose `currentpage` / `maxpage` to
     * `pic/key_shortcut.js`. The order is `maxpage` first, then
     * `currentpage` — preserved verbatim.
     */
    public static function keyShortcutScript(int $page = 1, int $pages = 1, string $nonce = ''): string
    {
        $currentpage = 'var currentpage='.$page.';';
        $maxpage = 'var maxpage='.$pages.';';

        return "\n".ltrim(view('components.support.tag.key-shortcut', [
            'nonce' => $nonce,
            'maxpage' => $maxpage,
            'currentpage' => $currentpage,
        ])->render(), "\n");
    }

    /**
     * Promotion type `<option>` list (no surrounding `<select>` —
     * the caller provides that). Backs the legacy `promotion_selection()`
     * helper. `$labels` keys: `normal`, `free`, `two_times_up`,
     * `free_two_times_up`, `half_down`, `half_down_two_up`,
     * `thirty_percent_down`. Missing keys degrade to empty strings.
     *
     * @param  array<string, string>  $labels
     */
    public static function promotionSelectOptions(int $selected, int $hide, array $labels): SafeHtml
    {
        $options = [
            1 => 'normal',
            2 => 'free',
            3 => 'two_times_up',
            4 => 'free_two_times_up',
            5 => 'half_down',
            6 => 'half_down_two_up',
            7 => 'thirty_percent_down',
        ];

        $html = '';
        foreach ($options as $id => $key) {
            if ($hide === $id) {
                continue;
            }
            $label = (string) ($labels[$key] ?? '');
            $html .= trim(view('components.support.tag.option', [
                'value' => $id,
                'selected' => $selected === $id,
                'label' => $label,
            ])->render());
        }

        return SafeHtml::fromTrustedHtml($html);
    }

    /**
     * Full-width labelled settings/usercp row. Backs legacy `tr($head,
     * $follow, $noesc, $relation, $return)`. Returns the HTML string;
     * the legacy proxy still handles the print-vs-return switch.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - When `$escape` is true the follow cell is `htmlspecialchars`-
     *    escaped AND `\n` → `<br />\n`-substituted (in that order).
     *  - `$head` is NEVER escaped — call sites pass lang strings and
     *    pre-built HTML markup (radio buttons, etc.).
     *  - A non-empty `$relation` is emitted as TWO attributes on the
     *    `<tr>`: `relation="X" class="X"` (both Blade-escaped).
     *  - The output has NO trailing newline.
     */
    public static function settingsRow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        $cell = $escape
            ? nl2br(htmlspecialchars($follow))
            : $follow;

        return trim(view('components.support.tag.settings-row', [
            'relation' => $relation,
            'head' => SafeHtml::fromTrustedHtml($head),
            'cell' => SafeHtml::fromTrustedHtml($cell),
        ])->render());
    }

    /**
     * Grid-mode counterpart of {@see settingsRow()}: emits the label and
     * value as `nx-fhead`/`nx-fcell` divs for `.nx-fgrid` hosts. When
     * `$relation` is set the pair is wrapped in `.nx-grouprow` carrying
     * the `relation` + class attributes so upload.js mode toggles keep
     * working. Same `$escape` semantics (\n → <br /> on escaped output).
     */
    public static function settingsFrow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        $cell = $escape
            ? nl2br(htmlspecialchars($follow))
            : $follow;

        return trim(view('components.support.tag.settings-frow', [
            'relation' => $relation,
            'head' => SafeHtml::fromTrustedHtml($head),
            'cell' => SafeHtml::fromTrustedHtml($cell),
        ])->render());
    }

    /**
     * Emit a settings row, returning it when `$return` is true or
     * echoing it otherwise. Backs the legacy `tr()` helper.
     */
    public static function emitSettingsRow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        $html = self::settingsRow($head, $follow, $escape, $relation);
        if ($return) {
            return $html;
        }
        echo $html;

        return null;
    }

    /**
     * Emit a grid-mode settings row ({@see settingsFrow()}), returning it
     * when `$return` is true or echoing it otherwise.
     */
    public static function emitSettingsFrow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        $html = self::settingsFrow($head, $follow, $escape, $relation);
        if ($return) {
            return $html;
        }
        echo $html;

        return null;
    }

    /**
     * Build a `<select>` of promotion types with localized labels.
     * Backs the legacy `promotion_selection()` helper.
     */
    public static function promotionSelection(int $selected = 0, int $hide = 0): SafeHtml
    {
        $labels = [
            'normal' => (string) (__('functions.text_normal')),
            'free' => (string) (__('functions.text_free')),
            'two_times_up' => (string) (__('functions.text_two_times_up')),
            'free_two_times_up' => (string) (__('functions.text_free_two_times_up')),
            'half_down' => (string) (__('functions.text_half_down')),
            'half_down_two_up' => (string) (__('functions.text_half_down_two_up')),
            'thirty_percent_down' => (string) (__('functions.text_thirty_percent_down')),
        ];

        return self::promotionSelectOptions($selected, $hide, $labels);
    }

    /**
     * Render a hidden `<div>` container holding one `<div>` child per
     * id/content pair. Backs the legacy `create_tooltip_container()`
     * helper used by `public/forums.php` (last-post tooltips) and
     * `public/offers.php` (last-comment tooltips); the visible page
     * uses `jQuery.tipsy` to clone these children on hover.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - Empty input ⇒ `''`. The legacy `if (count(...))` guard
     *    silently swallowed empty arrays without emitting the outer
     *    wrapper; callers rely on that to keep the page noise-free
     *    when no tooltip targets exist.
     *  - `content` is NOT escaped. Tooltip content is pre-built HTML
     *    markup (a `<table>` with the post body / user info, sometimes
     *    with `<img>` + `<a>` children) — escaping would corrupt it.
     *    IDs (`lastpost-12345` style) are Blade-escaped as attribute
     *    values.
     *  - Outer wrapper is always `<div class="nx-hidden">` —
     *    visibility is toggled per-child by tipsy at hover time.
     *
     * @param  iterable<array{id?: mixed, content?: mixed}>  $items
     * @param  int  $width  Legacy argument, ignored (tooltips are sized by CSS)
     */
    public static function tooltipContainer(iterable $items, int $width = 400): string
    {
        $children = [];
        foreach ($items as $item) {
            // Passed as View objects — the container renders (and
            // trims) each via {{ }} instead of a trusted wrap.
            $children[] = view('components.support.tag.tooltip-child', [
                'id' => (string) ($item['id'] ?? ''),
                'content' => SafeHtml::fromTrustedHtml((string) ($item['content'] ?? '')),
            ]);
        }
        if ($children === []) {
            return '';
        }

        return trim(view('components.support.tag.tooltip-container', ['children' => $children])->render());
    }

    /**
     * Build the quick-reply textarea + smiley row + submit button.
     *
     * Mirrors `quickreply()`.
     */
    public static function quickReply(string $formName, string $textareaName, string $submitLabel): string
    {
        return trim(view('components.support.tag.quick-reply', [
            'name' => $textareaName,
            'label' => (string) (__('functions.row_body')),
            'smileRow' => SafeHtml::fromTrustedHtml(Smilies::quickRow($formName, $textareaName)),
            'submitLabel' => $submitLabel,
        ])->render());
    }

    /**
     * Wrap a BBCode `[url]` tag in a temp-code placeholder.
     *
     * Backs the legacy `formatUrl()` helper.
     */
    public static function formatUrl(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        return Comment::addTempCode(BBCode::url($url, $newWindow, $text, $linkClass));
    }

    /**
     * Filter and render a `[img]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatImg()` helper.
     */
    public static function formatImg(string $src, bool $enableResizer, int $maxWidth, int $maxHeight, string $imgId = ''): string
    {
        // See BBCode::url() — tempCode placeholders (\x08…\x08) must not
        // reach the src attribute.
        $src = Security::filterSrc(str_replace("\x08", '', $src));
        if (empty($src)) {
            return '';
        }

        return Comment::addTempCode(BBCode::img($src, $enableResizer, $maxWidth, $maxHeight, $imgId));
    }

    /**
     * Filter and render a `[youtube]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatYoutube()` helper.
     */
    public static function formatYoutube(string $src, int|string $width = '', int|string $height = ''): string
    {
        // See BBCode::url() — tempCode placeholders (\x08…\x08) must not
        // reach the src attribute.
        $src = Security::filterSrc(str_replace("\x08", '', $src));
        if (empty($src)) {
            return '';
        }

        return Comment::addTempCode(BBCode::youtube($src, $width, $height));
    }

    /**
     * Render a `[spoiler]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatSpoiler()` helper.
     */
    public static function formatSpoiler(string $content, string $title = '', bool $defaultCollapsed = true): string
    {
        $defaultTitle = __('functions.spoiler_default_title');

        return Comment::addTempCode(BBCode::spoiler($content, $title, $defaultTitle, $defaultCollapsed));
    }

    /**
     * Wrap content in a BBCode [hidden] block with temp-code protection.
     *
     * Consolidated from HtmlRenderer::formatHidden().
     */
    public static function formatHidden(string $content): string
    {
        return Comment::addTempCode(BBCode::hidden($content)->toHtml());
    }

    /**
     * Wrap content in a BBCode text-align block with temp-code protection.
     *
     * Consolidated from HtmlRenderer::formatTextAlign().
     */
    public static function formatTextAlign(string $text, string $align): string
    {
        return Comment::addTempCode(BBCode::textAlign($text, $align));
    }
}
