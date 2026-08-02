<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path('index.php'));
loggedinorreturn(true);

$userid = (int)$CURUSER["id"];

\Nexus\Nexus::css('styles/index2.css', 'header', true);
\Nexus\Nexus::js('https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', 'footer', true);

$dashboard = \App\Repositories\IndexRepository::getDashboardData($userid);
$latest = \App\Repositories\IndexRepository::getLatestTorrents(9);
$trending = \App\Repositories\IndexRepository::getTrendingTorrents(6);
$snatched = \App\Repositories\IndexRepository::getMostSnatchedTorrents(6);
$chartData = \App\Repositories\IndexRepository::getChartData();

$chartJson = json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
\Nexus\Nexus::js("window.INDEX2_CHARTS = $chartJson;", 'footer', false);
\Nexus\Nexus::js('js/index2.js', 'footer', true);

/**
 * @param \App\Models\Torrent $torrent
 * @param array<string, string> $lang
 */
$renderTorrentCard = function ($torrent) use ($lang_index) {
    $detailsUrl = 'details.php?id=' . (int)$torrent->id . '&hit=1';
    $rawCover = trim((string)($torrent->cover ?? ''));
    $thumbUrl = $rawCover !== '' ? cover_thumb_url($rawCover, 240, 360) : '';
    $nameSafe = htmlspecialchars($torrent->name);
    $typeLabel = trim((string)($torrent->basic_category->name ?? ''));

    $badges = [];
    $promotionInfo = $torrent->promotionInfo;
    if ($promotionInfo && !empty($promotionInfo['text']) && $promotionInfo['text'] !== 'Normal') {
        $style = !empty($promotionInfo['color']) ? ' style="background:' . htmlspecialchars($promotionInfo['color']) . '; color:#fff;"' : '';
        $badges[] = '<span class="i2-badge"' . $style . '>' . htmlspecialchars($promotionInfo['text']) . '</span>';
    }
    if (!empty($torrent->pos_state) && $torrent->pos_state !== 'normal') {
        $badges[] = '<span class="i2-badge i2-badge-sticky">Sticky</span>';
    }
    if (!empty($torrent->added) && $torrent->added->gt(\Carbon\Carbon::now()->subDays(7))) {
        $badges[] = '<span class="i2-badge i2-badge-new">NEW</span>';
    }

    $ownerHtml = (($torrent->anonymous ?? 'no') === 'yes') ? '<i>Anonymous</i>' : get_username((int)$torrent->owner);

    echo '<div class="i2-card">';
    echo '<a class="i2-card-cover" href="' . htmlspecialchars($detailsUrl) . '" title="' . $nameSafe . '">';
    if ($thumbUrl !== '') {
        echo '<img src="' . htmlspecialchars($thumbUrl) . '" alt="' . $nameSafe . '" loading="lazy" onerror="this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'flex\';}" />';
        echo '<div class="i2-card-cover-fallback" style="display:none;">' . htmlspecialchars(mb_substr($torrent->name, 0, 60)) . '</div>';
    } else {
        echo '<div class="i2-card-cover-fallback">' . htmlspecialchars(mb_substr($torrent->name, 0, 60)) . '</div>';
    }
    if ($typeLabel !== '') {
        echo '<span class="i2-card-type">' . htmlspecialchars($typeLabel) . '</span>';
    }
    if (!empty($badges)) {
        echo '<div class="i2-card-badges">' . implode('', $badges) . '</div>';
    }
    echo '</a>';
    echo '<div class="i2-card-title"><a href="' . htmlspecialchars($detailsUrl) . '"><b>' . $nameSafe . '</b></a></div>';
    echo '<div class="i2-card-meta">';
    echo '<span class="i2-seed" title="' . htmlspecialchars($lang_index['col_seeder'] ?? 'Seeder') . '">&#x25B2; ' . number_format((int)$torrent->seeders) . '</span>';
    echo '<span class="i2-leech" title="' . htmlspecialchars($lang_index['col_leecher'] ?? 'Leecher') . '">&#x25BC; ' . number_format((int)$torrent->leechers) . '</span>';
    echo '<span>' . mksize((int)$torrent->size) . '</span>';
    echo '<span>' . $ownerHtml . '</span>';
    echo '</div>';
    echo '</div>';
};

stdhead($lang_index['head_home'] ?? 'Home');
begin_main_frame();
?>

<div class="i2-wrap">

<section class="i2-section i2-dashboard">
    <h2><?php echo htmlspecialchars($lang_index['text_dashboard'] ?? 'Dashboard') ?></h2>
    <div class="i2-dash-grid">
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"<?php echo $dashboard['ratio_color'] ? ' style="color:' . htmlspecialchars($dashboard['ratio_color']) . '"' : '' ?>>
                <?php echo $dashboard['ratio_html'] ?>
            </span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_ratio'] ?? 'Ratio') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo mksize($dashboard['uploaded']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_uploaded'] ?? 'Uploaded') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo mksize($dashboard['downloaded']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_downloaded'] ?? 'Downloaded') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo $dashboard['bonus'] ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_seed_bonus'] ?? 'Seed Bonus') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo $dashboard['seed_points'] ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_seed_points'] ?? 'Seed Points') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo number_format($dashboard['unread_pm_count']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_unread_messages'] ?? 'Unread PMs') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo number_format($dashboard['seeding_count']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_seeding'] ?? 'Seeding') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo number_format($dashboard['leeching_count']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_leeching'] ?? 'Leeching') ?></span>
        </div>
        <div class="i2-dash-card">
            <span class="i2-dash-card__value"><?php echo mksize($dashboard['seeding_size']) ?></span>
            <span class="i2-dash-card__label"><?php echo htmlspecialchars($lang_index['text_seeding_size'] ?? 'Seeding Size') ?></span>
        </div>
    </div>

    <div class="i2-dash-row">
        <div class="i2-dash-col">
            <h3><?php echo htmlspecialchars($lang_index['text_active_torrents'] ?? 'Active Torrents') ?></h3>
            <?php if (!empty($dashboard['active_torrents']) && $dashboard['active_torrents']->isNotEmpty()): ?>
                <ul class="i2-active-list">
                <?php foreach ($dashboard['active_torrents'] as $peer): ?>
                    <?php
                    /** @var \App\Models\Torrent|null $torrent */
                    $torrent = $peer->relative_torrent;
                    if (!$torrent) {
                        continue;
                    }
                    $progress = $peer->isSeeder() ? 100 : (100 - min(100, ((int)$peer->to_go / max(1, (int)$torrent->size)) * 100));
                    ?>
                    <li>
                        <div>
                            <a href="details.php?id=<?php echo (int)$torrent->id ?>&amp;hit=1"><?php echo htmlspecialchars(mb_substr($torrent->name, 0, 45)) ?></a>
                            <div class="i2-active-meta">
                                <?php echo $peer->isSeeder() ? 'Seeding' : 'Leeching' ?>
                                &middot;
                                <?php echo $peer->last_action ? $peer->last_action->diffForHumans() : '' ?>
                                &middot;
                                <?php echo number_format($progress, 1) ?>%
                            </div>
                        </div>
                        <div class="i2-active-meta"><?php echo mksize((int)$torrent->size) ?></div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php echo htmlspecialchars($lang_index['text_no_active_torrents'] ?? 'You have no active torrents.') ?></p>
            <?php endif; ?>
        </div>

        <div class="i2-dash-col">
            <h3><?php echo htmlspecialchars($lang_index['text_exams'] ?? 'Exams') ?></h3>
            <?php if (!empty($dashboard['exams']) && $dashboard['exams']->isNotEmpty()): ?>
                <ul class="i2-exam-list">
                <?php foreach ($dashboard['exams'] as $eu): ?>
                    <?php
                    $exam = $eu->exam;
                    $progress = $eu->progress_formatted;
                    ?>
                    <li class="i2-exam">
                        <div class="i2-exam-name"><?php echo htmlspecialchars($exam->name ?? '') ?></div>
                        <?php foreach ($progress as $item): ?>
                            <div class="i2-progress-row">
                                <span><?php echo htmlspecialchars($item['index_formatted'] ?? $item['name'] ?? '') ?></span>
                                <span class="<?php echo !empty($item['passed']) ? 'i2-passed' : 'i2-failed' ?>">
                                    <?php echo htmlspecialchars($item['current_value_formatted'] ?? '') ?> / <?php echo htmlspecialchars($item['require_value_formatted'] ?? '') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php echo htmlspecialchars($lang_index['text_no_exams'] ?? 'No active exams.') ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="i2-section i2-torrents">
    <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_last_five_torrent'] ?? 'Latest Torrents') ?></h2>
    <div class="i2-card-grid">
        <?php foreach ($latest as $torrent): $renderTorrentCard($torrent); endforeach; ?>
    </div>

    <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_trending'] ?? 'Trending') ?></h2>
    <div class="i2-card-grid">
        <?php foreach ($trending as $torrent): $renderTorrentCard($torrent); endforeach; ?>
    </div>

    <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_most_snatched'] ?? 'Most Snatched') ?></h2>
    <div class="i2-card-grid">
        <?php foreach ($snatched as $torrent): $renderTorrentCard($torrent); endforeach; ?>
    </div>
</section>

<section class="i2-section i2-stats">
    <h2><?php echo htmlspecialchars($lang_index['text_tracker_statistics'] ?? 'Tracker Statistics') ?></h2>
    <div class="i2-stats-grid">
        <div class="i2-stat">
            <span class="i2-stat__value"><?php echo number_format($chartData['total_users']) ?></span>
            <span class="i2-stat__label"><?php echo htmlspecialchars($lang_index['text_total_users'] ?? 'Users') ?></span>
        </div>
        <div class="i2-stat">
            <span class="i2-stat__value"><?php echo number_format($chartData['total_torrents']) ?></span>
            <span class="i2-stat__label"><?php echo htmlspecialchars($lang_index['text_total_torrents'] ?? 'Torrents') ?></span>
        </div>
        <div class="i2-stat">
            <span class="i2-stat__value"><?php echo number_format($chartData['total_peers']) ?></span>
            <span class="i2-stat__label"><?php echo htmlspecialchars($lang_index['text_total_peers'] ?? 'Peers') ?></span>
        </div>
        <div class="i2-stat">
            <span class="i2-stat__value"><?php echo mksize($chartData['total_uploaded']) ?></span>
            <span class="i2-stat__label"><?php echo htmlspecialchars($lang_index['text_total_uploaded'] ?? 'Uploaded') ?></span>
        </div>
        <div class="i2-stat">
            <span class="i2-stat__value"><?php echo mksize($chartData['total_downloaded']) ?></span>
            <span class="i2-stat__label"><?php echo htmlspecialchars($lang_index['text_total_downloaded'] ?? 'Downloaded') ?></span>
        </div>
    </div>

    <div class="i2-charts-grid">
        <div class="i2-chart"><canvas id="chart-class"></canvas></div>
        <div class="i2-chart"><canvas id="chart-seeders"></canvas></div>
        <div class="i2-chart"><canvas id="chart-monthly-users"></canvas></div>
        <div class="i2-chart"><canvas id="chart-monthly-torrents"></canvas></div>
        <div class="i2-chart"><canvas id="chart-traffic"></canvas></div>
    </div>
</section>

</div>

<?php
\App\Models\User::where('id', $userid)->update(['last_home' => now()]);
end_main_frame();
stdfoot();
?>
