<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AssetAppender;

/**
 * Registers the shoutbox/toast assets for the index page.
 * Split out of IndexPageService to keep the page service under the
 * RepositorySizeTest baseline.
 */
final class IndexToastAssetBuilder
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     */
    public function append(array $lang, array $curUser): void
    {
        $toastLang = json_encode([
            'newMessage' => $lang['toast_new_message'] ?? 'New message',
            'shoutboxMention' => $lang['toast_shoutbox_mention'] ?? 'Shoutbox mention',
            'from' => $lang['toast_from'] ?? 'From',
            'close' => $lang['toast_close'] ?? 'Close',
            'userId' => (int) ($curUser['id'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        AssetAppender::css('styles/shoutbox.css', 'header', true);
        AssetAppender::js('js/shoutbox.js', 'footer', true);
        AssetAppender::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
        AssetAppender::css('styles/toast.css', 'header', true);
        AssetAppender::js('js/toast.js', 'footer', true);
    }
}
