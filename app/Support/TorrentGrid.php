<?php

namespace App\Support;

use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\TagRepository;
use App\Models\TorrentTag;

final class TorrentGrid
{
    public const VIEWS = ['table', 'card', 'compact'];

    private const PROMOTION_BADGES = [
        2 => ['class' => 'free', 'text_key' => 'text_free'],
        3 => ['class' => 'twoup', 'text_key' => 'text_two_times_up'],
        4 => ['class' => 'twoupfree', 'text_key' => 'text_free_two_times_up'],
        5 => ['class' => 'halfdown', 'text_key' => 'text_half_down'],
        6 => ['class' => 'twouphalfdown', 'text_key' => 'text_half_down_two_up'],
        7 => ['class' => 'thirtypercent', 'text_key' => 'text_thirty_percent_down'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function render(array $rows, string $view = 'card', int $searchBoxId = 0): string
    {
        if (!in_array($view, self::VIEWS, true)) {
            $view = 'card';
        }

        if ($view === 'table') {
            return TorrentTable::render($rows, 'torrent', $searchBoxId);
        }

        global $CURUSER, $lang_functions, $Cache;

        $torrentIdArr = array_column($rows, 'id');
        $tagRep = new TagRepository();
        $torrentTagCollection = TorrentTag::query()->whereIn('torrent_id', $torrentIdArr)->get();
        $torrentTagResult = $torrentTagCollection->groupBy('torrent_id');

        $showCover = false;
        if ($searchBoxId) {
            $searchBoxExtra = get_searchbox_value($searchBoxId, 'extra');
            $showCover = !empty($searchBoxExtra[SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST]);
        }

        $lastBrowse = $CURUSER['last_browse'] ?? 0;
        if ($lastBrowse > TIMENOW) {
            $lastBrowse = TIMENOW;
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::renderItem($row, $view, $showCover, $lastBrowse, $torrentTagResult, $tagRep, $lang_functions);
        }

        if ($items === []) {
            return '';
        }

        $class = $view === 'compact' ? 't2-list t2-list--compact' : 't2-grid';
        return '<div class="' . $class . '">' . implode('', $items) . '</div>';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  mixed  $torrentTagResult
     * @param  array<string, string>  $langFunctions
     */
    private static function renderItem(
        array $row,
        string $view,
        bool $showCover,
        int $lastBrowse,
        $torrentTagResult,
        TagRepository $tagRep,
        array $langFunctions
    ): string {
        $id = (int) $row['id'];
        $name = htmlspecialchars((string) $row['name']);
        $cover = $showCover && !empty($row['cover']) ? (string) $row['cover'] : '';
        $categoryImage = '';
        if (!empty($row['category'])) {
            $categoryImage = return_category_image((int) $row['category'], '?');
        }

        $badges = self::badges($row, $lastBrowse, $langFunctions);
        $stats = sprintf(
            '<span class="t2-seeders" title="%s">%s</span> / <span class="t2-leechers" title="%s">%s</span> · %s · %s',
            $langFunctions['title_number_of_seeders'] ?? 'Seeders',
            number_format((int) $row['seeders']),
            $langFunctions['title_number_of_leechers'] ?? 'Leechers',
            number_format((int) $row['leechers']),
            mksize((float) $row['size']),
            gettime((string) $row['added'], false, true)
        );

        $tagOwns = $torrentTagResult->get($id);
        $tags = $tagOwns ? $tagRep->renderSpan((int) ($row['search_box_id'] ?? 0), $tagOwns->pluck('tag_id')->toArray()) : '';

        if ($view === 'compact') {
            return self::compactItem($id, $name, $cover, $categoryImage, $badges, $stats, $tags);
        }

        return self::cardItem($id, $name, $cover, $categoryImage, $badges, $stats, $tags);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $langFunctions
     */
    private static function badges(array $row, int $lastBrowse, array $langFunctions): string
    {
        global $CURUSER;
        $badges = [];

        if (!empty($row['banned']) && $row['banned'] === 'yes') {
            $badges[] = '<span class="t2-badge t2-badge--banned">' . ($langFunctions['text_banned'] ?? 'Banned') . '</span>';
        }

        $posState = $row['pos_state'] ?? '';
        if ($posState && $posState !== Torrent::POS_STATE_STICKY_NONE) {
            $posStates = Torrent::listPosStates();
            if (isset($posStates[$posState])) {
                $badges[] = '<span class="t2-badge t2-badge--sticky">' . htmlspecialchars($posStates[$posState]['text']) . '</span>';
            }
        }

        $globalSpState = get_global_sp_state();
        $effectiveCode = $globalSpState == 1 ? (int) ($row['sp_state'] ?? 1) : $globalSpState;
        if (isset(self::PROMOTION_BADGES[$effectiveCode])) {
            $config = self::PROMOTION_BADGES[$effectiveCode];
            $text = $langFunctions[$config['text_key']] ?? $config['class'];
            $badges[] = '<span class="t2-badge t2-badge--' . $config['class'] . '">' . htmlspecialchars($text) . '</span>';
        }

        if (!empty($row['added']) && strtotime((string) $row['added']) >= $lastBrowse && ($CURUSER['appendnew'] ?? '') !== 'no') {
            $badges[] = '<span class="t2-badge t2-badge--new">' . ($langFunctions['text_new_uppercase'] ?? 'NEW') . '</span>';
        }

        return $badges === [] ? '' : '<div class="t2-badges">' . implode('', $badges) . '</div>';
    }

    private static function coverHtml(string $cover, string $categoryImage, string $name, int $id): string
    {
        if ($cover) {
            return sprintf(
                '<a class="t2-cover" href="details2.php?id=%d&amp;hit=1"><img src="%s" alt="%s" loading="lazy" /></a>',
                $id,
                htmlspecialchars($cover),
                $name
            );
        }

        return sprintf(
            '<a class="t2-cover t2-cover--placeholder" href="details2.php?id=%d&amp;hit=1">%s</a>',
            $id,
            $categoryImage ?: '<span>' . $name . '</span>'
        );
    }

    private static function cardItem(int $id, string $name, string $cover, string $categoryImage, string $badges, string $stats, string $tags): string
    {
        $html = '<article class="t2-card">';
        $html .= self::coverHtml($cover, $categoryImage, $name, $id);
        $html .= '<div class="t2-card-body">';
        $html .= $badges;
        $html .= '<h3 class="t2-title"><a href="details2.php?id=' . $id . '&amp;hit=1">' . $name . '</a></h3>';
        if ($tags) {
            $html .= '<div class="t2-tags">' . $tags . '</div>';
        }
        $html .= '<div class="t2-stats">' . $stats . '</div>';
        $html .= '</div></article>';
        return $html;
    }

    private static function compactItem(int $id, string $name, string $cover, string $categoryImage, string $badges, string $stats, string $tags): string
    {
        $html = '<article class="t2-card t2-card--compact">';
        $html .= self::coverHtml($cover, $categoryImage, $name, $id);
        $html .= '<div class="t2-card-body">';
        $html .= '<h3 class="t2-title"><a href="details2.php?id=' . $id . '&amp;hit=1">' . $name . '</a></h3>';
        $html .= $badges;
        if ($tags) {
            $html .= '<div class="t2-tags">' . $tags . '</div>';
        }
        $html .= '<div class="t2-stats">' . $stats . '</div>';
        $html .= '</div></article>';
        return $html;
    }
}
