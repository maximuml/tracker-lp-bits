<?php

declare(strict_types=1);

namespace App\Support\Html;

use App\Support\Frame;
use App\Support\Language;
use App\Support\Smilies;

/**
 * Message and alert helpers extracted from the legacy Html facade.
 *
 * Pure string-building functions — no DI, no DB, no config, no global state
 * (except where legacy helpers like Language/Smilies are invoked).
 */
final class Message
{
    /**
     * Render the banner-style "site alert" box used by
     * `app/Utils/MsgAlert::render()` (Filament side channel) and the
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
        $safeUrl = htmlspecialchars($url, ENT_QUOTES);
        $inner = $url !== ''
            ? '<b><a href="'.$safeUrl.'" target=\'_blank\'><font color="white">'.$text.'</font></a></b>'
            : '<b><font color="white">'.$text.'</font></b>';

        return '<table border="0" cellspacing="0" cellpadding="10" style="margin: 0 auto;"><tr><td style=\'border: none; padding: 10px; background: '.htmlspecialchars($bgcolor, ENT_QUOTES).'; text-align: center;\'>'."\n".$inner.'</td></tr></table><br />';
    }

    /**
     * Emit a banner-style site alert. Backs the legacy `msgalert()` helper.
     */
    public static function messageAlertVoid(string $url, string $text, string $bgcolor = 'red'): void
    {
        echo self::messageAlert($url, $text, $bgcolor);
    }

    /**
     * Emit a standard message box. Backs the legacy `stdmsg()` helper.
     */
    public static function stdMessage(string $heading, string $text, bool $htmlstrip = false): void
    {
        echo Frame::stdMessage($heading, $text, $htmlstrip);
    }

    /**
     * Emit a quick-reply form block. Backs the legacy `quickreply()` helper.
     */
    public static function quickReplyVoid(string $formName, string $textareaName, string $submitLabel): void
    {
        echo Tag::quickReply($formName, $textareaName, $submitLabel);
    }

    /**
     * Emit the framed smilies table. Backs the legacy `insert_smilies_frame()` helper.
     */
    public static function smiliesFrame(): void
    {
        $lang = app(Language::class)->functions();

        echo Smilies::framedTable(
            (string) ($lang['text_smilies'] ?? ''),
            (string) ($lang['col_type_something'] ?? ''),
            (string) ($lang['col_to_make_a'] ?? ''),
        );
    }
}
