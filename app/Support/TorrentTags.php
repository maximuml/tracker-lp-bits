<?php

namespace App\Support;

use App\Auth\Permission;
use App\Models\Tag;
use App\Models\TorrentTag;
use Nexus\Database\NexusDB;

/**
 * Legacy torrent-tag helpers extracted from `include/functions.php`.
 *
 * Backs `insert_torrent_tags()` and `torrentTags()`.
 */
final class TorrentTags
{
    /**
     * Persist tag assignments for a torrent.
     *
     * Mirrors `insert_torrent_tags()`.
     *
     * @param  array<int, int>  $tagIdArr
     */
    public static function insert(int|string $torrentId, array $tagIdArr, bool $sync = false): void
    {
        $specialTags = Tag::listSpecial();
        $canSetSpecialTag = Permission::canSetTorrentSpecialTag();
        $dateTimeStringNow = date('Y-m-d H:i:s');

        if ($sync) {
            $delQuery = TorrentTag::query()->where('torrent_id', $torrentId);
            if (! $canSetSpecialTag) {
                $delQuery->whereNotIn('tag_id', $specialTags);
            }
            $delQuery->delete();
        }

        if (empty($tagIdArr)) {
            return;
        }

        $records = [];
        foreach ($tagIdArr as $tagId) {
            if (in_array($tagId, $specialTags) && ! $canSetSpecialTag) {
                \do_log("special tag: $tagId, and user no permission");
                continue;
            }
            if (! isset($records[$tagId])) {
                $records[$tagId] = [
                    'torrent_id' => $torrentId,
                    'tag_id' => $tagId,
                    'created_at' => $dateTimeStringNow,
                    'updated_at' => $dateTimeStringNow,
                ];
            }
        }

        if (empty($records)) {
            return;
        }

        \do_log("[INSERT_TAGS], torrent: $torrentId with tags: " . nexus_json_encode($tagIdArr));
        TorrentTag::query()->insert(array_values($records));
    }

    /**
     * Render tag checkboxes or tag spans.
     *
     * Mirrors `torrentTags()`.
     *
     * @param  array<string, string>  $labels
     */
    public static function render(int|string $tags = 0, string $type = 'checkbox', array $labels = []): string
    {
        $tagsOptions = [
            ['text' => $labels['text_tag_no_release_to_any_other'] ?? '', 'color' => '#ff0000'],
            ['text' => $labels['text_tag_first_release'] ?? '', 'color' => '#8F77B5'],
            ['text' => $labels['text_tag_official'] ?? '', 'color' => '#0000ff'],
            ['text' => $labels['text_tag_diy'] ?? '', 'color' => '#46d5ff'],
            ['text' => $labels['text_tag_mother_language'] ?? '', 'color' => '#6a3906'],
            ['text' => $labels['text_tag_mother_language_subtitle'] ?? '', 'color' => '#006400'],
            ['text' => $labels['text_tag_hdr'] ?? '', 'color' => '#38b03f'],
        ];

        $tags = (int) $tags;
        $html = '';
        foreach ($tagsOptions as $key => $value) {
            $currentValue = (int) pow(2, $key);
            if ($type === 'checkbox') {
                $checked = ($currentValue & $tags) ? 'checked' : '';
                $html .= sprintf(
                    '<label><input type="checkbox" name="tags[]" value="%s" %s />%s</label>',
                    $currentValue,
                    $checked,
                    $value['text']
                );
            } elseif ($type === 'span' && ($currentValue & $tags)) {
                $html .= "<span style=\"background-color:{$value['color']};color:white;border-radius:15%\">{$value['text']}</span> ";
            }
        }

        return $html;
    }
}
