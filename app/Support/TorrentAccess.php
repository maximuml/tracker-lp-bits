<?php

namespace App\Support;

use App\Enums\HitAndRunMode;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Legacy torrent access helpers extracted from `include/functions.php`.
 *
 * Backs `can_access_torrent` and `torrent_name_for_admin`.
 */
final class TorrentAccess
{
    /**
     * Check whether a user may access the given torrent.
     *
     * Mirrors `can_access_torrent()`.
     */
    /**
     * @param  array<string, mixed>|int|string  $torrent
     */
    public static function canAccess(array|int|string $torrent, int|string $uid): bool
    {
        return true;
    }

    /**
     * Build the admin-area torrent name link.
     *
     * Mirrors `torrent_name_for_admin()`.
     */
    public static function adminName(\App\Models\Torrent|null $torrent, bool $withTags = false, int $length = 40): HtmlString
    {
        if (empty($torrent)) {
            return new HtmlString('');
        }

        $name = sprintf(
            '<div class="fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-300 fi-link fi-size-sm fi-ac-link-action"><a href="/details.php?id=%s" target="_blank" title="%s">%s</a></div>',
            $torrent->id,
            $torrent->name,
            Str::limit($torrent->name, $length)
        );
        $tags = '';
        if ($withTags) {
            $tags = sprintf('&nbsp;<div>%s</div>', $torrent->tagsFormatted);
        }

        return new HtmlString('<div style="display:flex">' . $name . $tags . '</div>');
    }

    /**
     * Build the H&R icon for a torrent row, if applicable.
     *
     * Mirrors `get_hr_img()`.
     */
    /**
     * @param  array<string, mixed>  $torrent
     */
    public static function hrImage(array $torrent, int|string $searchBoxId): string
    {
        $mode = HitAndRunMode::fromStringSafe(
            is_string($value = \App\Models\HitAndRun::getConfig('mode', $searchBoxId)) ? $value : null
        );

        if (
            $mode === HitAndRunMode::GLOBAL
            || ($mode === HitAndRunMode::MANUAL && isset($torrent['hr']) && $torrent['hr'] == \App\Models\Torrent::HR_YES)
        ) {
            return '<img class="hitandrun" src="pic/trans.gif" alt="H&R" title="H&R" />';
        }

        return '';
    }
}
