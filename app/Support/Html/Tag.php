<?php

declare(strict_types=1);

namespace App\Support\Html;

use App\Support\BBCode;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Comment;
use App\Support\SearchBox;
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
     * Build a `<tr><td>…</td>…</tr>\n` row. Legacy `EchoRow($class, ...$cells)`
     * returns the bare `<tr></tr>` (with no trailing newline) when no
     * cells are supplied; that bare-row contract is preserved.
     */
    public static function tableRow(string $class, string ...$cells): string
    {
        $classAttr = $class !== '' ? sprintf(' class="%s"', $class) : '';
        $row = trim(view('support.tag._tr', [
            'classAttr' => SafeHtml::fromTrustedHtml($classAttr),
            'cells' => array_map(SafeHtml::fromTrustedHtml(...), $cells),
        ])->render());

        return $cells === [] ? $row : $row."\n";
    }

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
        $nonceAttr = $nonce !== '' ? ' nonce="'.htmlspecialchars($nonce, ENT_QUOTES).'"' : '';

        return "\n".ltrim(view('support.tag._key-shortcut', [
            'nonceAttr' => SafeHtml::fromTrustedHtml($nonceAttr),
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
    public static function promotionSelectOptions(int $selected, int $hide, array $labels): string
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
            $html .= trim(view('support.tag._option', [
                'value' => $id,
                'selected' => $selected === $id,
                'label' => SafeHtml::fromTrustedHtml($label),
            ])->render());
        }

        return $html;
    }

    /**
     * Full labelled torrent attribute `<select>` block — `<b>NAME</b>`
     * prefix, "choose one" default `<option value="0">`, then one
     * `<option>` per item. Backs the legacy `torrent_selection()`
     * helper. The DB lookup that produces `$items` stays in the
     * proxy because `searchbox_item_list()` is DB-backed.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$name` and `$selectName` are NOT escaped — call sites pass
     *    plain lang strings, never user input.
     *  - Item names ARE `htmlspecialchars`-escaped (PHP 8.1+ default
     *    flags), matching the legacy emitter.
     *  - The trailing `&nbsp;&nbsp;&nbsp;\n` after `</select>` is
     *    intentional spacing in the source markup.
     *
     * @param  iterable<array{id?: mixed, name?: mixed}>  $items
     */
    public static function torrentSelect(
        string $name,
        string $selectName,
        string $chooseOneLabel,
        int $selectedId,
        iterable $items,
    ): string {
        $options = [];
        foreach ($items as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $options[] = SafeHtml::fromTrustedHtml(trim(view('support.tag._option', [
                'value' => $rowId,
                'selected' => $rowId === $selectedId,
                'label' => SafeHtml::fromTrustedHtml(htmlspecialchars((string) ($row['name'] ?? ''))),
            ])->render()));
        }

        return ltrim(view('support.tag._torrent-select', [
            'name' => SafeHtml::fromTrustedHtml($name),
            'selectName' => SafeHtml::fromTrustedHtml($selectName),
            'chooseOneLabel' => SafeHtml::fromTrustedHtml($chooseOneLabel),
            'options' => $options,
        ])->render(), "\n");
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
     *    `<tr>`: `relation="X" class="X"`. The value is used unescaped.
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

        $relationAttr = $relation !== ''
            ? sprintf(' relation="%s" class="%s"', $relation, $relation)
            : '';

        return trim(view('support.tag._settings-row', [
            'relationAttr' => SafeHtml::fromTrustedHtml($relationAttr),
            'head' => SafeHtml::fromTrustedHtml($head),
            'cell' => SafeHtml::fromTrustedHtml($cell),
        ])->render());
    }

    /**
     * Narrow-label variant of {@see settingsRow()}. Backs legacy
     * `tr_small()`. The two `<td>` cells carry `nx-w-1p`/`nx-w-99p`
     * so the label hugs its content while the value
     * stretches; otherwise the row shape is identical.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$escape` controls `htmlspecialchars` on `$follow` BUT does
     *    NOT trigger the `\n` → `<br />\n` substitution. The legacy
     *    source has that line commented out — kept that way because
     *    `usercp.php` passes pre-built `<select>` / `<input>` markup
     *    that should not gain `<br />` for embedded newlines.
     *  - Non-empty `$relation` is emitted as a SINGLE attribute with
     *    surrounding spaces: ` relation = "X"`. (Different shape
     *    from {@see settingsRow()} — both are preserved verbatim.)
     */
    public static function settingsRowSmall(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        $cell = $escape ? htmlspecialchars($follow) : $follow;

        $relationAttr = $relation !== '' ? ' relation = "'.$relation.'"' : '';

        return trim(view('support.tag._settings-row-small', [
            'relationAttr' => SafeHtml::fromTrustedHtml($relationAttr),
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

        return trim(view('support.tag._settings-frow', [
            'relation' => SafeHtml::fromTrustedHtml($relation),
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
     * Two bare `<td>` cells (no `<tr>` wrap) — the inner half of a
     * legacy `twotd()` call, used by `public/index.php`'s stats panel
     * to glue two cells into an already-open `<tr>`.
     *
     * Legacy quirk preserved: the original `twotd($x, $y, $nosec=0)`
     * computed `htmlspecialchars($y)` into a local `$a` when `$nosec`
     * was falsy but then printed `$y` (unescaped) anyway — the escape
     * result was dead code. The proxy still accepts the third
     * parameter for ABI compatibility but the emitted string is
     * always `$follow` verbatim.
     */
    public static function settingsCells(string $head, string $follow): string
    {
        return trim(view('support.tag._settings-cells', [
            'head' => SafeHtml::fromTrustedHtml($head),
            'follow' => SafeHtml::fromTrustedHtml($follow),
        ])->render());
    }

    /**
     * Build a `<select>` for searchbox taxonomy items. Backs the legacy
     * `torrent_selection()` helper.
     */
    public static function torrentSelection(string $name, string $selName, string $listName, int $selectedId = 0, int $mode = 0): string
    {
        $items = SearchBox::itemList(LegacyRedisCache::instance(), $listName, $mode);
        $chooseOne = __('functions.select_choose_one');

        return self::torrentSelect($name, $selName, $chooseOne, $selectedId, $items);
    }

    /**
     * Build a `<select>` of promotion types with localized labels.
     * Backs the legacy `promotion_selection()` helper.
     */
    public static function promotionSelection(int $selected = 0, int $hide = 0): string
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
     *  - Neither `id` NOR `content` is escaped. Tooltip content is
     *    pre-built HTML markup (a `<table>` with the post body / user
     *    info, sometimes with `<img>` + `<a>` children) — escaping
     *    would corrupt it. Pre-`<div>`-extracted IDs are trusted
     *    integer-derived strings (`lastpost-12345` style). Same
     *    contract as the legacy.
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
            $children[] = SafeHtml::fromTrustedHtml(trim(view('support.tag._tooltip-child', [
                'id' => SafeHtml::fromTrustedHtml((string) ($item['id'] ?? '')),
                'content' => SafeHtml::fromTrustedHtml((string) ($item['content'] ?? '')),
            ])->render()));
        }
        if ($children === []) {
            return '';
        }

        return trim(view('support.tag._tooltip-container', ['children' => $children])->render());
    }

    /**
     * Render a simple full-width data table. Backs the legacy
     * `build_table($header, $rows, $options)` helper.
     *
     * `$header` is a `columnKey => columnLabel` map: the labels build
     * the `<thead>` cells, and the *keys* are used to pull each cell
     * value out of every row (so column order follows `$header`, not
     * the row's own key order). A missing key yields an empty cell
     * (legacy `$row[$key] ?? ''`).
     *
     * Legacy quirks preserved bit-for-bit:
     *  - neither labels nor cell values are escaped — call sites pass
     *    pre-built markup / trusted strings;
     *  - header cells are always `class="colhead"`; body cells take
     *    `class="colfollow"` only when `$options['td-center']` is
     *    truthy, otherwise an empty `class=""`.
     *
     * @param  array<array-key, string>  $header
     * @param  iterable<array<array-key, mixed>>  $rows
     * @param  array<string, mixed>  $options
     */
    public static function buildTable(array $header, iterable $rows, array $options = []): string
    {
        $tdClass = ! empty($options['td-center']) ? 'colfollow' : '';

        return trim(view('support.tag._build-table', [
            'header' => $header,
            'rows' => $rows,
            'tdClass' => $tdClass,
        ])->render());
    }

    /**
     * Build the quick-reply textarea + smiley row + submit button.
     *
     * Mirrors `quickreply()`.
     */
    public static function quickReply(string $formName, string $textareaName, string $submitLabel): string
    {
        return trim(view('support.tag._quick-reply', [
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
        $src = Security::filterSrc($src);
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
        $src = Security::filterSrc($src);
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
        return Comment::addTempCode((string) BBCode::hidden($content));
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
