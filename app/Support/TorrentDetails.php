<?php

namespace App\Support;

use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentRepository;
use Nexus\Database\NexusDB;
use Nexus\Torrent\Torrent as TorrentService;

/**
 * Modern, card-style renderer for the torrent details page (details2.php).
 */
final class TorrentDetails
{
    /**
     * @param  array<int|string, mixed>  $row
     */
    public static function render(array $row, ?string $returnto = null): string
    {
        global $CURUSER, $Cache, $lang_details, $lang_functions;

        $id = (int) $row['id'];
        $torrentRep = new TorrentRepository();
        $searchBoxRep = new SearchBoxRepository();
        $tagRep = new \App\Repositories\TagRepository();
        $torrentService = new TorrentService();

        $owned = user_can('torrentmanage') || (int) $CURUSER['id'] === (int) $row['owner'];
        $canDownload = $CURUSER['downloadpos'] !== 'no';

        $cover = Torrent::query()->where('id', $id)->value('cover') ?? '';
        $tags = self::tagsHtml($row, $tagRep);
        $badges = self::badgesHtml($row, $torrentRep);
        $stats = self::statsHtml($row);

        $toolbar = self::toolbarHtml($row, $owned, $canDownload, $torrentRep, $returnto);
        $sidebar = self::sidebarHtml($row, $torrentRep, $torrentService);

        $overview = self::overviewHtml($row, $searchBoxRep);
        $mediaInfo = self::mediaInfoHtml($row);
        $files = self::filesHtml($id, $row);
        $peers = self::peersHtml($id, $row);
        $comments = self::commentsHtml($id, $row);
        $similar = self::similarHtml($id, $row);

        $tabs = '';
        if ($mediaInfo !== '') {
            $tabs .= '<button class="d2-tab" data-tab="mediainfo">MediaInfo</button>';
        }

        $jsData = htmlspecialchars(json_encode([
            'torrent_id' => $id,
            'is_bookmarked' => in_array($id, TorrentBookmark::bookmarkArray($Cache, (int) $CURUSER['id']), false),
            'has_thanked' => false,
            'has_given_magic' => false,
            'user_bonus' => (float) $CURUSER['seedbonus'],
        ]), ENT_QUOTES, 'UTF-8');

        ob_start();
?>
<div class="d2-wrapper" data-details2="<?php echo $jsData ?>">
    <?php if ($row['banned'] === 'yes') { ?>
    <div class="d2-banner d2-banner--danger"><?php echo $lang_functions['text_banned'] ?></div>
    <?php } ?>
    <?php echo self::approvalBanner($row) ?>

    <section class="d2-hero">
        <?php if ($cover) { ?>
        <div class="d2-cover">
            <img src="<?php echo htmlspecialchars($cover) ?>" alt="" loading="lazy" />
        </div>
        <?php } ?>
        <div class="d2-hero-main">
            <h1 class="d2-title"><?php echo htmlspecialchars($row['name']) ?></h1>
            <div class="d2-badges"><?php echo $badges ?></div>
            <?php if ($tags !== '') { ?>
            <div class="d2-tags"><?php echo $tags ?></div>
            <?php } ?>
            <div class="d2-stats"><?php echo $stats ?></div>
            <?php echo $toolbar ?>
        </div>
    </section>

    <div class="d2-layout">
        <main class="d2-main">
            <nav class="d2-tabs">
                <button class="d2-tab d2-tab--active" data-tab="overview"><?php echo $lang_details['row_description'] ?? 'Overview' ?></button>
                <?php echo $tabs ?>
                <button class="d2-tab" data-tab="files"><?php echo $lang_details['text_files'] ?? 'Files' ?> (<?php echo number_format((int) $row['numfiles']) ?>)</button>
                <button class="d2-tab" data-tab="peers"><?php echo $lang_details['row_peers'] ?? 'Peers' ?> (<?php echo number_format((int) $row['seeders']) ?>/<?php echo number_format((int) $row['leechers']) ?>)</button>
                <button class="d2-tab" data-tab="comments"><?php echo $lang_details['h1_user_comments'] ?? 'Comments' ?></button>
            </nav>

            <div class="d2-tab-panels">
                <div class="d2-panel d2-panel--active" data-panel="overview">
                    <?php echo $overview ?>
                </div>
                <?php if ($mediaInfo !== '') { ?>
                <div class="d2-panel" data-panel="mediainfo">
                    <?php echo $mediaInfo ?>
                </div>
                <?php } ?>
                <div class="d2-panel" data-panel="files">
                    <?php echo $files ?>
                </div>
                <div class="d2-panel" data-panel="peers">
                    <?php echo $peers ?>
                </div>
                <div class="d2-panel" data-panel="comments">
                    <?php echo $comments ?>
                </div>
            </div>
        </main>

        <aside class="d2-sidebar">
            <?php echo $sidebar ?>
            <?php if ($similar !== '') { ?>
            <section class="d2-card">
                <h3><?php echo $lang_functions['text_similar_torrents'] ?? 'Similar Torrents' ?></h3>
                <?php echo $similar ?>
            </section>
            <?php } ?>
        </aside>
    </div>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function tagsHtml(array $row, \App\Repositories\TagRepository $tagRep): string
    {
        $torrentTags = TorrentTag::query()->where('torrent_id', (int) $row['id'])->get();
        if ($torrentTags->isEmpty()) {
            return '';
        }
        return $tagRep->renderSpan((int) $row['search_box_id'], $torrentTags->pluck('tag_id')->toArray());
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function badgesHtml(array $row, TorrentRepository $torrentRep): string
    {
        global $lang_functions;

        $out = '';
        if ($row['banned'] === 'yes') {
            $out .= '<span class="d2-badge d2-badge--danger">' . $lang_functions['text_banned'] . '</span>';
        }
        $out .= get_torrent_promotion_append(
            (int) $row['sp_state'],
            'word',
            false,
            (string) $row['added'],
            (int) ($row['promotion_time_type'] ?? 0),
            (string) ($row['promotion_until'] ?? ''),
            (bool) ($row['__ignore_global_sp_state'] ?? false)
        );
        $out .= get_torrent_promotion_append_sub(
            (int) $row['sp_state'],
            '',
            true,
            (string) $row['added'],
            (int) ($row['promotion_time_type'] ?? 0),
            (string) ($row['promotion_until'] ?? ''),
            (bool) ($row['__ignore_global_sp_state'] ?? false)
        );
        $out .= $torrentRep->getPaidIcon($row, 20);
        $out .= get_hr_img($row, (int) $row['search_box_id']);
        $out .= $torrentRep->renderApprovalStatus((int) $row['approval_status']);

        global $CURUSER;
        if (($CURUSER['appendnew'] ?? 'yes') !== 'no' && strtotime((string) $row['added']) >= (int) ($CURUSER['last_browse'] ?? 0)) {
            $out .= '<span class="d2-badge d2-badge--new">' . ($lang_functions['text_new_uppercase'] ?? 'NEW') . '</span>';
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function statsHtml(array $row): string
    {
        global $lang_details;

        $added = gettime((string) $row['added'], true, false, true);
        $lastSeeder = gettime((string) $row['last_action'], true, false, true);

        return sprintf(
            '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value d2-seeders">%s</span></div>'
            . '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value d2-leechers">%s</span></div>'
            . '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value">%s</span></div>'
            . '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value">%s</span></div>'
            . '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value">%s</span></div>'
            . '<div class="d2-stat"><span class="d2-stat-label">%s</span><span class="d2-stat-value">%s</span></div>',
            trim($lang_details['text_seeders'] ?? 'Seeders', " :"), number_format((int) $row['seeders']),
            trim($lang_details['text_leechers'] ?? 'Leechers', " :"), number_format((int) $row['leechers']),
            trim($lang_details['text_snatched'] ?? 'Snatched', " :"), number_format((int) $row['times_completed']),
            trim($lang_details['text_size'] ?? 'Size', " :"), mksize((float) $row['size']),
            trim($lang_details['text_added'] ?? 'Added', " :"), $added,
            trim($lang_details['row_last_seeder'] ?? 'Last active', " :"), $lastSeeder
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function toolbarHtml(array $row, bool $owned, bool $canDownload, TorrentRepository $torrentRep, ?string $returnto = null): string
    {
        global $CURUSER, $Cache, $lang_details, $lang_functions;

        $id = (int) $row['id'];
        $actions = [];

        if ($canDownload) {
            $hasBuy = ($row['price'] ?? 0) > 0
                ? \App\Models\TorrentBuyLog::query()->where('uid', (int) $CURUSER['id'])->where('torrent_id', $id)->exists()
                : true;
            if (($row['price'] ?? 0) > 0) {
                $downloadText = $hasBuy
                    ? ($lang_details['text_download_bought_torrent'] ?? 'Download bought torrent')
                    : sprintf($lang_details['text_download_paid_torrent'] ?? 'Buy for %s', number_format((int) $row['price']));
            } else {
                $downloadText = htmlspecialchars($GLOBALS['torrentnameprefix'] ?? '') . '.' . htmlspecialchars((string) ($row['save_as'] ?? 'torrent')) . '.torrent';
            }
            $actions[] = '<a class="d2-btn d2-btn--primary" href="download.php?id=' . $id . '"><span class="d2-icon">&#x2193;</span> ' . $downloadText . '</a>';
            $actions[] = '<button type="button" class="d2-btn d2-btn--secondary" id="d2-copy-url">' . ($lang_details['text_copy_url'] ?? 'Copy URL') . '</button>';
        }

        $isBookmarked = in_array($id, TorrentBookmark::bookmarkArray($Cache, (int) $CURUSER['id']), false);
        $bookmarkLabel = $isBookmarked
            ? ($lang_functions['title_delbookmark_torrent'] ?? 'Remove bookmark')
            : ($lang_functions['title_bookmark_torrent'] ?? 'Add bookmark');
        $actions[] = '<button type="button" class="d2-btn d2-btn--secondary" id="d2-bookmark" data-bookmarked="' . ($isBookmarked ? '1' : '0') . '"><span class="d2-icon">' . ($isBookmarked ? '&#x2605;' : '&#x2606;') . '</span> ' . $bookmarkLabel . '</button>';

        if ($owned) {
            $editUrl = 'edit.php?id=' . $id;
            if ($returnto !== null && $returnto !== '') {
                $editUrl .= '&returnto=' . rawurlencode($returnto);
            }
            $actions[] = '<a class="d2-btn d2-btn--secondary" href="' . htmlspecialchars($editUrl) . '"><span class="d2-icon">&#x270E;</span> ' . ($lang_details['text_edit_torrent'] ?? 'Edit') . '</a>';
            if (user_can('torrent-delete')) {
                $actions[] = '<a class="d2-btn d2-btn--danger" href="' . htmlspecialchars('fastdelete.php?id=' . $id) . '" onclick="return confirm(\'' . addslashes($lang_functions['text_delete'] ?? 'Delete?') . '\')"><span class="d2-icon">&#x2715;</span> ' . ($lang_functions['text_delete'] ?? 'Delete') . '</a>';
            }
        }

        $actions[] = '<a class="d2-btn d2-btn--secondary" href="report.php?torrent=' . $id . '"><span class="d2-icon">&#x26A0;</span> ' . ($lang_details['text_report_torrent'] ?? 'Report') . '</a>';

        if (user_can('askreseed') && (int) $row['seeders'] === 0) {
            $actions[] = '<a class="d2-btn d2-btn--secondary" href="takereseed.php?reseedid=' . $id . '"><span class="d2-icon">&#x21BB;</span> ' . ($lang_details['text_ask_for_reseed'] ?? 'Ask for reseed') . '</a>';
        }

        if (user_can('torrent-approval') && (get_setting('torrent.approval_status_icon_enabled') === 'yes' || get_setting('torrent.approval_status_none_visible') === 'no')) {
            $actions[] = '<button type="button" class="d2-btn d2-btn--secondary" id="d2-approve" data-torrent_id="' . $id . '"><span class="d2-icon">&#x2713;</span> ' . ($lang_details['action_approval'] ?? 'Approval') . '</button>';
        }

        $torrentUrl = $torrentRep->getDownloadUrl($id, $CURUSER);
        $actions[] = '<button type="button" class="d2-btn d2-btn--secondary" id="d2-copy-hash" data-hash="' . htmlspecialchars(bin2hex(hash_pad($row['info_hash']))) . '"><span class="d2-icon">&#x2398;</span> Info hash</button>';

        /** @var array<int, string> $actions */
        $actions = apply_filter('torrent_detail_actions', $actions, $row);

        return '<div class="d2-toolbar" data-torrent-url="' . htmlspecialchars($torrentUrl) . '">' . implode('', $actions) . '</div>';
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function sidebarHtml(array $row, TorrentRepository $torrentRep, TorrentService $torrentService): string
    {
        global $CURUSER, $lang_details, $lang_functions;

        $id = (int) $row['id'];

        if ($row['anonymous'] === 'yes') {
            if (!user_can('viewanonymous') && (int) $row['owner'] !== (int) $CURUSER['id']) {
                $uploader = '<i>' . ($lang_details['text_anonymous'] ?? 'Anonymous') . '</i>';
            } else {
                $uploader = '<i>' . ($lang_details['text_anonymous'] ?? 'Anonymous') . '</i> (' . get_username((int) $row['owner'], false, true, true, false, false, false) . ')';
            }
        } else {
            $uploader = get_username((int) ($row['owner'] ?? 0), false, true, true, false, false, false);
        }

        $infoHash = bin2hex(hash_pad($row['info_hash']));

        $rows = [];
        $rows[] = ['label' => $lang_details['row_upped_by'] ?? 'Uploader', 'value' => $uploader];
        $rows[] = ['label' => $lang_details['row_type'] ?? 'Type', 'value' => htmlspecialchars((string) ($row['cat_name'] ?? '-'))];
        $rows[] = ['label' => $lang_details['text_size'] ?? 'Size', 'value' => mksize((float) $row['size'])];
        $rows[] = ['label' => $lang_details['text_added'] ?? 'Added', 'value' => gettime((string) $row['added'], false, true)];
        $rows[] = ['label' => $lang_details['row_last_seeder'] ?? 'Last active', 'value' => gettime((string) $row['last_action'])];
        $infoHashValue = '<code id="d2-info-hash">' . htmlspecialchars($infoHash) . '</code> <button type="button" class="d2-btn d2-btn--small" id="d2-copy-hash-only">' . ($lang_functions['text_copy'] ?? 'Copy') . '</button>';
        if (user_can('torrentstructure')) {
            $infoHashValue .= ' <a href="torrent_info.php?id=' . $id . '">' . ($lang_details['text_torrent_info_note'] ?? 'Torrent structure') . '</a>';
        }
        $rows[] = ['label' => $lang_details['row_info_hash'] ?? 'Info hash', 'value' => $infoHashValue];

        $progress = '';
        $status = $torrentService->listLeechingSeedingStatus((int) $CURUSER['id'], [$id])[$id] ?? null;
        if ($status) {
            $progress = $torrentService->renderProgressBar($status['active_status'], $status['progress']);
        }

        $bonus = self::bonusHtml($row);
        $thanks = self::thanksHtml($row);

        ob_start();
?>
<section class="d2-card">
    <h3><?php echo $lang_details['text_torrent_info'] ?? 'Torrent info' ?></h3>
    <dl class="d2-dl">
        <?php foreach ($rows as $r) { ?>
        <dt><?php echo $r['label'] ?></dt>
        <dd><?php echo $r['value'] ?></dd>
        <?php } ?>
    </dl>
    <?php if ($progress !== '') { ?>
    <div class="d2-progress-wrap"><?php echo $lang_functions['text_your_status'] ?? 'Your status' ?>: <?php echo $progress ?></div>
    <?php } ?>
</section>

<section class="d2-card">
    <h3><?php echo $lang_details['magic_value_award'] ?? 'Give Bonus' ?></h3>
    <?php echo $bonus ?>
</section>

<section class="d2-card">
    <h3><?php echo $lang_details['row_thanks_by'] ?? 'Thanks' ?></h3>
    <?php echo $thanks ?>
</section>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function overviewHtml(array $row, SearchBoxRepository $searchBoxRep): string
    {
        global $CURUSER, $lang_details, $lang_functions;

        $taxonomyInfo = $searchBoxRep->listTaxonomyInfo((int) $row['search_box_id'], $row);
        $taxonomyHtml = '';
        foreach ($taxonomyInfo as $item) {
            $taxonomyHtml .= sprintf('<div class="d2-meta-item"><span class="d2-meta-label">%s</span><span class="d2-meta-value">%s</span></div>', htmlspecialchars($item['label']), $item['value']);
        }

        $description = '';
        if (($CURUSER['showdescription'] ?? 'yes') !== 'no' && !empty($row['descr'])) {
            $description = (string) apply_filter('torrent_detail_description', format_comment($row['descr']), (int) $row['id'], (int) $CURUSER['id']);
        }

        $customFields = '';
        if (class_exists(\Nexus\Field\Field::class)) {
            $customField = new \Nexus\Field\Field();
            $customFields = $customField->renderOnTorrentDetailsPage((int) $row['id'], (int) $row['search_box_id']);
        }

        $infoTds = [];
        $infoTds[] = '<div class="d2-meta-item"><span class="d2-meta-label">' . ($lang_details['text_num_files'] ?? 'Files') . '</span><span class="d2-meta-value">' . number_format((int) $row['numfiles']) . '</span></div>';
        $infoTds[] = '<div class="d2-meta-item"><span class="d2-meta-label">' . ($lang_details['text_views'] ?? 'Views') . '</span><span class="d2-meta-value">' . number_format((int) $row['views']) . '</span></div>';
        $infoTds[] = '<div class="d2-meta-item"><span class="d2-meta-label">' . ($lang_details['text_hits'] ?? 'Hits') . '</span><span class="d2-meta-value">' . number_format((int) $row['hits']) . '</span></div>';
        $infoTds[] = '<div class="d2-meta-item"><span class="d2-meta-label">' . ($lang_details['text_snatched'] ?? 'Snatched') . '</span><span class="d2-meta-value"><a href="viewsnatches.php?id=' . (int) $row['id'] . '">' . number_format((int) $row['times_completed']) . '</a></span></div>';

        ob_start();
?>
<div class="d2-overview">
    <div class="d2-meta-grid">
        <?php echo $taxonomyHtml ?>
        <?php echo implode('', $infoTds) ?>
    </div>
    <?php echo $customFields ?>
    <?php do_action('torrent_detail_before_desc', (int) $row['id'], (int) $CURUSER['id']); ?>
    <?php if ($description !== '') { ?>
    <div class="d2-description">
        <h3><?php echo $lang_details['row_description'] ?? 'Description' ?></h3>
        <div class="d2-description-body">
            <?php echo $description ?>
        </div>
    </div>
    <?php } ?>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function mediaInfoHtml(array $row): string
    {
        if (get_setting('main.enable_technical_info') !== 'yes') {
            return '';
        }
        $technicalData = nexus_escape($row['technical_info'] ?? '');
        if (empty($technicalData)) {
            return '';
        }

        $firstLine = strtok($technicalData, "\n");
        $isBdInfo = strpos($firstLine, 'DISC INFO') !== false
            || strpos($firstLine, 'Disc Title') !== false
            || strpos($firstLine, 'Disc Label') !== false;

        $technicalInfo = $isBdInfo
            ? new \Nexus\Torrent\BdInfoExtra($technicalData)
            : new \Nexus\Torrent\TechnicalInformation($technicalData);
        $result = $technicalInfo->renderOnDetailsPage();
        if (empty($result)) {
            return '';
        }

        return '<div class="d2-mediainfo">' . $result . '</div>';
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function filesHtml(int $id, array $row): string
    {
        global $lang_details;
        return '<div class="d2-files" data-files-loaded="0" data-torrent-id="' . $id . '">'
            . '<p>' . trim($lang_details['text_num_files'] ?? 'Files', " :") . ': ' . number_format((int) $row['numfiles']) . '</p>'
            . '<button type="button" class="d2-btn d2-btn--secondary d2-load-files">' . ($lang_details['text_see_full_list'] ?? 'Show files') . '</button>'
            . '<div class="d2-files-content"></div>'
            . '</div>';
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function peersHtml(int $id, array $row): string
    {
        global $lang_details;
        $seeders = number_format((int) $row['seeders']);
        $leechers = number_format((int) $row['leechers']);
        return '<div class="d2-peers" data-peers-loaded="0" data-torrent-id="' . $id . '">'
            . '<p><b>' . trim($lang_details['text_seeders'] ?? 'Seeders', " :") . ':</b> ' . $seeders . ' | <b>' . trim($lang_details['text_leechers'] ?? 'Leechers', " :") . ':</b> ' . $leechers . '</p>'
            . '<button type="button" class="d2-btn d2-btn--secondary d2-load-peers">' . ($lang_details['text_see_full_list'] ?? 'Show peers') . '</button>'
            . '<div class="d2-peers-content"></div>'
            . '</div>';
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function commentsHtml(int $id, array $row): string
    {
        global $lang_details, $CURUSER;

        $commentsList = '';
        if (($CURUSER['showcomment'] ?? 'yes') !== 'no') {
            $count = TorrentDetailRepository::getCommentCount($id);
            if ($count === 0) {
                $commentsList = '<p>' . ($lang_details['text_no_comments_yet'] ?? 'No comments yet.') . '</p>';
            } else {
                list($pagertop, $pagerbottom, $limit, $offset, $rpp) = pager(
                    10,
                    $count,
                    'details2.php?id=' . $id . '&cmtpage=1&',
                    ['lastpagedefault' => 1],
                    'page'
                );
                $allrows = TorrentDetailRepository::getComments($id, (int) $offset, (int) $rpp);

                ob_start();
                echo $pagertop;
                commenttable($allrows, 'torrent', $id);
                echo $pagerbottom;
                $commentsList = ob_get_clean();
            }
        } else {
            $commentsList = '<p>' . ($lang_details['text_comments_hidden'] ?? 'Comments are hidden by your settings.') . '</p>';
        }

        return $commentsList . self::quickCommentForm($id, $row);
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function quickCommentForm(int $id, array $row): string
    {
        global $lang_details;
        ob_start();
?>
<div class="d2-quick-comment">
    <h3><?php echo $lang_details['text_quick_comment'] ?? 'Quick comment' ?></h3>
    <form id="compose" name="comment" method="post" action="<?php echo htmlspecialchars('comment.php?action=add&type=torrent') ?>" onsubmit="return postvalid(this);">
        <input type="hidden" name="pid" value="<?php echo $id ?>" />
        <?php quickreply('comment', 'body', $lang_details['submit_add_comment'] ?? 'Add comment') ?>
    </form>
    <p><a href="<?php echo htmlspecialchars('comment.php?action=add&pid=' . $id . '&type=torrent') ?>"><?php echo $lang_details['text_add_a_comment'] ?? 'Add a comment' ?></a></p>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function similarHtml(int $id, array $row): string
    {
        global $CURUSER;

        if (empty($row['category'])) {
            return '';
        }

        $torrents = Torrent::query()
            ->where('category', (int) $row['category'])
            ->where('id', '!=', $id)
            ->where('banned', 'no')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'seeders', 'leechers', 'size', 'cover'])
            ->toArray();

        if (empty($torrents)) {
            return '';
        }

        $items = [];
        foreach ($torrents as $t) {
            $t = (array) $t;
            if (!can_access_torrent((int) $t['id'], (int) $CURUSER['id'])) {
                continue;
            }
            $cover = $t['cover'] ? '<img src="' . htmlspecialchars($t['cover']) . '" alt="" loading="lazy" />' : '<div class="d2-no-cover"></div>';
            $items[] = '<a class="d2-similar" href="details2.php?id=' . (int) $t['id'] . '&hit=1">'
                . $cover
                . '<div class="d2-similar-info">'
                . '<div class="d2-similar-title">' . htmlspecialchars((string) $t['name']) . '</div>'
                . '<div class="d2-similar-meta"><span class="d2-seeders">' . number_format((int) $t['seeders']) . '</span> / <span class="d2-leechers">' . number_format((int) $t['leechers']) . '</span> &middot; ' . mksize((float) $t['size']) . '</div>'
                . '</div>'
                . '</a>';
        }

        return $items === [] ? '' : '<div class="d2-similar-list">' . implode('', $items) . '</div>';
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function bonusHtml(array $row): string
    {
        global $CURUSER, $lang_details;

        $id = (int) $row['id'];
        if ((int) $CURUSER['id'] === (int) $row['owner']) {
            return '<p>' . ($lang_details['text_cannot_give_bonus_to_self'] ?? 'You cannot give bonus to yourself.') . '</p>';
        }

        $magicInfo = TorrentDetailRepository::getMagicInfo($id, (int) $CURUSER['id']);
        $countUserNumber = $magicInfo['count_user_number'];
        $sumValue = $magicInfo['sum_value'];
        $whetherHaveGiveValue = $magicInfo['whether_have_give_value'];
        $addValue = $magicInfo['add_value'];

        $options = \App\Models\Setting::getBonusRewardOptions();
        $bonus = (float) $CURUSER['seedbonus'];
        $min = !empty($options) ? (int) array_values($options)[0] : PHP_INT_MAX;

        $givers = [];
        foreach ($magicInfo['givers'] as $giver) {
            $givers[] = get_username((int) $giver->userid, false, true, true, false, false, false);
        }

        $newestLimit = 6;
        $visibleGivers = array_slice($givers, 0, $newestLimit);
        $hiddenGivers = array_slice($givers, $newestLimit);

        ob_start();
?>
<div class="d2-bonus" data-torrent-id="<?php echo $id ?>">
    <?php if (!$whetherHaveGiveValue && $bonus >= $min) { ?>
    <div class="d2-bonus-options">
        <?php foreach ($options as $key => $value) { $val = (int) $value; if ($val > 0 && $val <= $bonus) { ?>
        <button type="button" class="d2-btn d2-btn--small d2-give-bonus" data-value="<?php echo $val ?>">+<?php echo number_format($val) ?></button>
        <?php } } ?>
    </div>
    <?php } elseif ($whetherHaveGiveValue) { ?>
    <p><?php echo sprintf($lang_details['magic_value_number'] ?? 'You have given %s bonus.', number_format((int) $addValue)) ?></p>
    <?php } else { ?>
    <p><?php echo $lang_details['magic_have_no_enough_bonus_value'] ?? 'Not enough bonus.' ?></p>
    <?php } ?>
    <p class="d2-bonus-total">
        <?php
            $gotBonus = $lang_details['magic_haveGotBonus'] ?? 'Total bonus received: Number';
            echo str_replace('Number', '<span id="d2-bonus-total">' . number_format((int) $sumValue) . '</span>', $gotBonus);
        ?>
        <?php if ($countUserNumber > 0) { ?>
        (<?php echo str_replace('Number', '<span id="d2-bonus-user-count">' . number_format((int) $countUserNumber) . '</span>', ($lang_details['magic_sum_user_give_number'] ?? 'Number users')) ?>)
        <?php } ?>
    </p>
    <?php if (!empty($givers)) { ?>
    <div class="d2-bonus-givers">
        <strong><?php echo $lang_details['magic_newest_record'] ?? 'Newest givers' ?>:</strong>
        <?php echo implode(' ', $visibleGivers) ?>
        <?php if (!empty($hiddenGivers)) { ?>
        <span class="d2-bonus-more" style="display:none"><?php echo implode(' ', $hiddenGivers) ?></span>
        <a href="javascript:void(0)" class="d2-show-all-bonus"><?php echo $lang_details['magic_show_all_description'] ?? '[Show all]' ?></a>
        <?php } ?>
    </div>
    <?php } ?>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function thanksHtml(array $row): string
    {
        global $CURUSER, $lang_details;

        $id = (int) $row['id'];
        $thanksInfo = TorrentDetailRepository::getThanksInfo($id, (int) $CURUSER['id']);
        $hasThanked = $thanksInfo['has_thanked'];

        if ((int) $CURUSER['id'] === (int) $row['owner']) {
            return '<p>' . ($lang_details['text_cannot_thank_self'] ?? 'You cannot thank yourself.') . '</p>';
        }

        $givers = [];
        foreach ($thanksInfo['thanks'] as $t) {
            $givers[] = get_username((int) $t->userid, false, true, true, false, false, false);
        }
        $giversHtml = $givers ? implode(', ', $givers) : ($lang_details['text_no_thanks_added'] ?? 'No thanks added yet.');

        ob_start();
?>
<div class="d2-thanks" data-torrent-id="<?php echo $id ?>">
    <button type="button" class="d2-btn d2-btn--secondary" id="d2-say-thanks" <?php echo $hasThanked ? 'disabled' : '' ?>>
        <?php echo $hasThanked ? ($lang_details['submit_you_said_thanks'] ?? 'You said thanks') : ($lang_details['submit_say_thanks'] ?? 'Say thanks') ?>
    </button>
    <p class="d2-thanks-list"><?php echo $giversHtml ?><?php if ($thanksInfo['count'] > count($thanksInfo['thanks'])) { echo ' ' . ($lang_details['text_and_more'] ?? 'and more') . ' ' . $thanksInfo['count'] . ' ' . ($lang_details['text_users_in_total'] ?? 'users in total'); } ?></p>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private static function approvalBanner(array $row): string
    {
        if ((int) $row['approval_status'] !== \App\Models\Torrent::APPROVAL_STATUS_DENY) {
            return '';
        }

        $log = \App\Models\TorrentOperationLog::query()
            ->where('torrent_id', (int) $row['id'])
            ->where('action_type', \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY)
            ->orderByDesc('id')
            ->first();

        if (!$log) {
            return '';
        }

        return '<div class="d2-banner d2-banner--danger">' . nexus_trans('torrent.approval.deny_comment_show', ['reason' => $log->comment]) . '</div>';
    }
}
