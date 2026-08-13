<?php

namespace App\Support;

/**
 * Stateless HTML-string emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state.
 */
final class Html
{
    /**
     * Build a `<tr><td>…</td>…</tr>\n` row. Legacy `EchoRow($class, ...$cells)`
     * returns the bare `<tr></tr>` (with no trailing newline) when no
     * cells are supplied; that bare-row contract is preserved.
     */
    /**
     * Build the quick-reply textarea + smiley row + submit button.
     *
     * Mirrors `quickreply()`.
     */
    public static function quickReply(string $formName, string $textareaName, string $submitLabel): string
    {
        $html = "<textarea name='" . $textareaName . "' cols=\"100\" rows=\"8\" style=\"width: 450px\" onkeydown=\"ctrlenter(event,'compose','qr')\"></textarea>";
        $html .= \App\Support\Smilies::quickRow($formName, $textareaName);
        $html .= '<br />';
        $html .= '<input type="submit" id="qr" class="btn" value="' . $submitLabel . '" />';

        return $html;
    }

    public static function tableRow(string $class, string ...$cells): string
    {
        if (count($cells) === 0) {
            return '<tr></tr>';
        }
        $classAttr = $class !== '' ? sprintf(' class="%s"', $class) : '';
        $td = '';
        foreach ($cells as $cell) {
            $td .= sprintf('<td%s>%s</td>', $classAttr, $cell);
        }

        return '<tr>'.$td."</tr>\n";
    }

    /**
     * Emit the `<script>` block that legacy `key_shortcut()` injects
     * into paginated views to expose `currentpage` / `maxpage` to
     * `pic/key_shortcut.js`. The order is `maxpage` first, then
     * `currentpage` — preserved verbatim.
     */
    public static function keyShortcutScript(int $page = 1, int $pages = 1): string
    {
        $currentpage = 'var currentpage='.$page.';';
        $maxpage = 'var maxpage='.$pages.';';

        return "\n<script type=\"text/javascript\">\n//<![CDATA[\n".$maxpage."\n".$currentpage."\n//]]>\n</script>\n";
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
            $selectedAttr = $selected === $id ? ' selected="selected"' : '';
            $label = (string) ($labels[$key] ?? '');
            $html .= '<option value="'.$id.'"'.$selectedAttr.'>'.$label.'</option>';
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
        $html = '<b>'.$name.'</b>&nbsp;<select name="'.$selectName.'">'."\n"
            .'<option value="0">'.$chooseOneLabel."</option>\n";
        foreach ($items as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $rowName = htmlspecialchars((string) ($row['name'] ?? ''));
            $selectedAttr = $rowId === $selectedId ? ' selected="selected"' : '';
            $html .= '<option value="'.$rowId.'"'.$selectedAttr.'>'.$rowName."</option>\n";
        }
        $html .= "</select>&nbsp;&nbsp;&nbsp;\n";

        return $html;
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
            ? str_replace("\n", "<br />\n", htmlspecialchars($follow))
            : $follow;

        $relationAttr = $relation !== ''
            ? sprintf(' relation="%s" class="%s"', $relation, $relation)
            : '';

        return sprintf(
            '<tr%s><td class="rowhead nowrap" valign="top" align="right">%s</td><td class="rowfollow" valign="top" align="left">%s</td></tr>',
            $relationAttr,
            $head,
            $cell,
        );
    }

    /**
     * Narrow-label variant of {@see settingsRow()}. Backs legacy
     * `tr_small()`. The two `<td>` cells carry `width="1%"` and
     * `width="99%"` so the label hugs its content while the value
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

        return '<tr'.$relationAttr.'><td width="1%" class="rowhead nowrap" valign="top" align="right">'.$head.'</td><td width="99%" class="rowfollow" valign="top" align="left">'.$cell.'</td></tr>';
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
     * Emit a narrow-label settings row. Backs the legacy `tr_small()` helper.
     */
    public static function emitSettingsRowSmall(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
        bool $return = false,
    ): ?string {
        $html = self::settingsRowSmall($head, $follow, $escape, $relation);
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
        return '<td class="rowhead">'.$head.'</td><td class="rowfollow">'.$follow.'</td>';
    }

    /**
     * Build a `<select>` for searchbox taxonomy items. Backs the legacy
     * `torrent_selection()` helper.
     */
    public static function torrentSelection(string $name, string $selName, string $listName, int $selectedId = 0, int $mode = 0): string
    {
        $items = \App\Support\SearchBox::itemList(SupportContext::getCache(), $listName, $mode);
        $chooseOne = SupportContext::getLangFunctions()['select_choose_one'] ?? '';

        return self::torrentSelect($name, $selName, $chooseOne, $selectedId, $items);
    }

    /**
     * Build a `<select>` of promotion types with localized labels.
     * Backs the legacy `promotion_selection()` helper.
     */
    public static function promotionSelection(int $selected = 0, int $hide = 0): string
    {
        $lang = SupportContext::getLangFunctions();
        $labels = [
            'normal' => (string) ($lang['text_normal'] ?? ''),
            'free' => (string) ($lang['text_free'] ?? ''),
            'two_times_up' => (string) ($lang['text_two_times_up'] ?? ''),
            'free_two_times_up' => (string) ($lang['text_free_two_times_up'] ?? ''),
            'half_down' => (string) ($lang['text_half_down'] ?? ''),
            'half_down_two_up' => (string) ($lang['text_half_down_two_up'] ?? ''),
            'thirty_percent_down' => (string) ($lang['text_thirty_percent_down'] ?? ''),
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
     *  - Outer wrapper is always `<div style="display: none">` —
     *    visibility is toggled per-child by tipsy at hover time.
     *
     * @param  iterable<array{id?: mixed, content?: mixed}>  $items
     * @param  int  $width  Legacy argument, ignored (tooltips are sized by CSS)
     */
    public static function tooltipContainer(iterable $items, int $width = 400): string
    {
        $children = '';
        foreach ($items as $item) {
            $children .= '<div id="'.(string) ($item['id'] ?? '').'">'.(string) ($item['content'] ?? '').'</div>';
        }
        if ($children === '') {
            return '';
        }

        return '<div style="display: none">'.$children.'</div>';
    }

    /**
     * Render the banner-style "site alert" box used by
     * `app/Utils/MsgAlert::render()` (Filament side-channel) and the
     * legacy stdhead path. Backs the legacy `msgalert()` helper.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - Non-empty `$url` ⇒ the message wraps in an `<a target="_blank">`
     *    link. Empty `$url` ⇒ just `<b>` + `<font color="white">` text.
     *  - `$url`, `$text`, and `$bgcolor` are NOT escaped — call sites
     *    pass pre-built markup or trusted lang strings.
     *  - The outer `<table>` uses inline-style `margin: 0 auto;` so the
     *    alert centres regardless of surrounding layout, and the
     *    trailing `<br />` separates stacked alerts.
     *  - Internal whitespace (the `\n` after the opening `<td>`) is
     *    preserved verbatim — the legacy emitted it as part of a
     *    multi-`print()` sequence.
     */
    public static function messageAlert(string $url, string $text, string $bgcolor = 'red'): string
    {
        $inner = $url !== ''
            ? '<b><a href="'.$url.'" target=\'_blank\'><font color="white">'.$text.'</font></a></b>'
            : '<b><font color="white">'.$text.'</font></b>';

        return '<table border="0" cellspacing="0" cellpadding="10" style="margin: 0 auto;"><tr><td style=\'border: none; padding: 10px; background: '.$bgcolor.'; text-align: center;\'>'."\n".$inner.'</td></tr></table><br />';
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
        $table = '<table border="1" cellspacing="0" cellpadding="5" width="100%"><thead><tr>';
        foreach ($header as $value) {
            $table .= sprintf('<td class="colhead">%s</td>', $value);
        }
        $table .= '</tr></thead><tbody>';

        $tdClass = ! empty($options['td-center']) ? 'colfollow' : '';

        foreach ($rows as $row) {
            $table .= '<tr>';
            foreach ($header as $headerKey => $headerValue) {
                $table .= sprintf('<td class="%s">%s</td>', $tdClass, $row[$headerKey] ?? '');
            }
            $table .= '</tr>';
        }

        return $table.'</tbody></table>';
    }

    /**
     * Wrap a BBCode `[url]` tag in a temp-code placeholder.
     *
     * Backs the legacy `formatUrl()` helper.
     */
    public static function formatUrl(string $url, bool $newWindow = false, string $text = '', string $linkClass = ''): string
    {
        return \App\Support\Comment::addTempCode(\App\Support\BBCode::url($url, $newWindow, $text, $linkClass));
    }

    /**
     * Filter and render a `[img]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatImg()` helper.
     */
    public static function formatImg(string $src, bool $enableResizer, int $maxWidth, int $maxHeight, string $imgId = ''): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::img($src, $enableResizer, $maxWidth, $maxHeight, $imgId));
    }

    /**
     * Filter and render a `[flash]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatFlash()` helper.
     */
    public static function formatFlash(string $src, int|string $width, int|string $height): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::flash($src, $width, $height));
    }

    /**
     * Filter and render a `[flv]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatFlv()` helper.
     */
    public static function formatFlv(string $src, int|string $width, int|string $height): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::flv($src, $width, $height));
    }

    /**
     * Filter and render a `[youtube]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatYoutube()` helper.
     */
    public static function formatYoutube(string $src, int|string $width = '', int|string $height = ''): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::youtube($src, $width, $height));
    }

    /**
     * Filter and render a `[video]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatVideo()` helper.
     */
    public static function formatVideo(string $src, int|string $width, int|string $height): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::video($src, $width, $height));
    }

    /**
     * Filter and render an `[audio]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatAudio()` helper.
     */
    public static function formatAudio(string $src): string
    {
        $src = \App\Support\Security::filterSrc($src);
        if (empty($src)) {
            return '';
        }

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::audio($src));
    }

    /**
     * Render a `[spoiler]` tag with a temp-code placeholder.
     *
     * Backs the legacy `formatSpoiler()` helper.
     */
    public static function formatSpoiler(string $content, string $title = '', bool $defaultCollapsed = true): string
    {
        $defaultTitle = SupportContext::getLangFunctions()['spoiler_default_title'] ?? '';

        return \App\Support\Comment::addTempCode(\App\Support\BBCode::spoiler($content, $title, $defaultTitle, $defaultCollapsed));
    }

    /**
     * Render the page header. Backs the legacy `stdhead()` helper.
     */
    public static function stdhead(string $title = "", bool $msgalert = true, string $script = "", string $place = ""): void
    {
        $context = PageLayoutContext::fromSupportContext();
        PageLayout::setContext($context);
        \App\Support\PageLayout::header($title, $msgalert, $script, $place);
    }

    /**
     * Render the page footer. Backs the legacy `stdfoot()` helper.
     */
    public static function stdfoot(): void
    {
        \App\Support\PageLayout::footer();
    }

    /**
     * Open a framed content block. Backs the legacy `begin_frame()` helper.
     */
    public static function beginFrame(string $caption = "", bool $center = false, int $padding = 10, string $width = "100%", string $caption_center = "left"): void
    {
        echo \App\Support\Frame::open($caption, $center, $padding, $width, $caption_center);
    }

    /**
     * Close a framed content block. Backs the legacy `end_frame()` helper.
     */
    public static function endFrame(): void
    {
        echo \App\Support\Frame::CLOSE;
    }

    /**
     * Open a data table. Backs the legacy `begin_table()` helper.
     */
    public static function beginTable(bool $fullwidth = false, int $padding = 5): void
    {
        echo \App\Support\Frame::tableOpen($fullwidth, $padding);
    }

    /**
     * Close a data table. Backs the legacy `end_table()` helper.
     */
    public static function endTable(): void
    {
        echo \App\Support\Frame::TABLE_CLOSE;
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

    /**
     * Emit a standard message box. Backs the legacy `stdmsg()` helper.
     */
    public static function stdMessage(string $heading, string $text, bool $htmlstrip = false): void
    {
        echo \App\Support\Frame::stdMessage($heading, $text, $htmlstrip);
    }

    /**
     * Emit a quick-reply form block. Backs the legacy `quickreply()` helper.
     */
    public static function quickReplyVoid(string $formName, string $textareaName, string $submitLabel): void
    {
        echo self::quickReply($formName, $textareaName, $submitLabel);
    }

    /**
     * Emit a banner-style site alert. Backs the legacy `msgalert()` helper.
     */
    public static function messageAlertVoid(string $url, string $text, string $bgcolor = 'red'): void
    {
        echo self::messageAlert($url, $text, $bgcolor);
    }

    /**
     * Emit the framed smilies table. Backs the legacy `insert_smilies_frame()` helper.
     */
    public static function smiliesFrame(): void
    {
        $lang = \App\Support\SupportContext::getLangFunctions();

        echo \App\Support\Smilies::framedTable(
            (string) ($lang['text_smilies'] ?? ''),
            (string) ($lang['col_type_something'] ?? ''),
            (string) ($lang['col_to_make_a'] ?? ''),
        );
    }
}
