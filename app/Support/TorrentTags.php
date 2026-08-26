<?php

namespace App\Support;

use App\Repositories\TagRepository;

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
        app(TagRepository::class)->syncTorrentTags($torrentId, $tagIdArr, $sync);
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

    /**
     * Context-aware wrapper for {@see render()}.
     */
    public static function renderWithContext(int|string $tags = 0, string $type = 'checkbox'): string
    {
        $lang = app(Language::class)->functions();

        return self::render($tags, $type, [
            'text_tag_no_release_to_any_other' => $lang['text_tag_no_release_to_any_other'] ?? '',
            'text_tag_first_release' => $lang['text_tag_first_release'] ?? '',
            'text_tag_official' => $lang['text_tag_official'] ?? '',
            'text_tag_diy' => $lang['text_tag_diy'] ?? '',
            'text_tag_mother_language' => $lang['text_tag_mother_language'] ?? '',
            'text_tag_mother_language_subtitle' => $lang['text_tag_mother_language_subtitle'] ?? '',
            'text_tag_hdr' => $lang['text_tag_hdr'] ?? '',
        ]);
    }
}
