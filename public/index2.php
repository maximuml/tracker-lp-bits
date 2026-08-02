<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path('index.php'));
loggedinorreturn(true);

$userid = (int)$CURUSER["id"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && $showpolls_main == "yes")
{
	$choice = $_POST["choice"] ?? '';
	if ($CURUSER && $choice !== '' && $choice < 256 && $choice == floor($choice))
	{
		$poll = \App\Repositories\IndexRepository::getCurrentPoll();
		if (!$poll)
			die($lang_index['std_no_poll'] ?? 'No poll');
		$pollid = $poll["id"];

		if (\App\Repositories\IndexRepository::hasVoted($pollid, $CURUSER["id"]))
			stderr($lang_index['std_error'] ?? 'Error', $lang_index['std_duplicate_votes_denied'] ?? 'Duplicate votes denied');
		if (!\App\Repositories\IndexRepository::recordPollVote($pollid, $CURUSER["id"], (int)$choice))
			stderr($lang_index['std_error'] ?? 'Error', $lang_index['std_vote_not_counted'] ?? 'Vote not counted');
		$Cache->delete_value('current_poll_content');
		$Cache->delete_value('current_poll_result', true);
		KPS("+",$pollvote_bonus,$userid);

		header("Location: " . get_protocol_prefix() . "$BASEURL/index2.php");
		die;
	}
	else
	{
		stderr($lang_index['std_error'] ?? 'Error', $lang_index['std_option_unselected'] ?? 'Option unselected');
	}
}

\Nexus\Nexus::css('styles/index2.css', 'header', true);
\Nexus\Nexus::css('styles/shoutbox.css', 'header', true);
\Nexus\Nexus::css('styles/toast.css', 'header', true);
\Nexus\Nexus::js('vendor/chart.js-4.4.3/chart.umd.min.js', 'footer', true);
\Nexus\Nexus::js('js/shoutbox.js', 'footer', true);
\Nexus\Nexus::js('js/toast.js', 'footer', true);

$toastLang = json_encode([
    'newMessage' => $lang_index['toast_new_message'] ?? 'New message',
    'shoutboxMention' => $lang_index['toast_shoutbox_mention'] ?? 'Shoutbox mention',
    'from' => $lang_index['toast_from'] ?? 'From',
    'close' => $lang_index['toast_close'] ?? 'Close',
    'userId' => (int) ($CURUSER['id'] ?? 0),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
\Nexus\Nexus::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
\Nexus\Nexus::js("var SHOUT_CSRF = '" . addslashes(\App\Support\Shoutbox::csrfToken((int)($CURUSER['id'] ?? 0))) . "';", 'footer', false);

$dashboard = \App\Repositories\IndexRepository::getDashboardData($userid);
$latest = \App\Repositories\IndexRepository::getLatestTorrents(9);
$trending = \App\Repositories\IndexRepository::getTrendingTorrents(6);
$snatched = \App\Repositories\IndexRepository::getMostSnatchedTorrents(6);
$chartData = \App\Repositories\IndexRepository::getChartData();

$userStats = \App\Repositories\IndexRepository::getUserStats();
$torrentStats = \App\Repositories\IndexRepository::getTorrentStats();
$classStats = \App\Repositories\IndexRepository::getClassStats();

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

/**
 * @param \Illuminate\Support\Collection<int, \App\Models\User> $uploaders
 * @param string $class
 * @param string $style
 * @param array<string, string> $lang
 */
$renderTopUploaderTable = function ($uploaders, $class, $style, $lang) {
    echo '<table class="i2-table ' . $class . '" width="100%" border="1" cellspacing="0" cellpadding="5"' . ($style ? ' style="' . $style . '"' : '') . '>';
    echo '<tr><td class="colhead">' . htmlspecialchars($lang['col_author'] ?? 'Author') . '</td><td class="colhead" align="center">' . htmlspecialchars($lang['col_counts'] ?? 'Counts') . '</td><td class="colhead" align="center">' . htmlspecialchars($lang['col_ranking'] ?? 'Ranking') . '</td></tr>';
    foreach ($uploaders as $ranking => $uploader) {
        echo '<tr><td>' . get_username($uploader->id) . '</td><td align="center">' . $uploader->count . '</td><td align="center">' . ($ranking + 1) . '</td></tr>';
    }
    echo '</table>';
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
                    if (!$exam) {
                        continue;
                    }
                    $progress = (new \App\Repositories\ExamRepository())->getProgressFormatted($exam, (array)$eu->progress);
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

<?php // ------------- start: recent news ------------------// ?>
<section class="i2-section i2-news">
    <h2><?php echo $lang_index['text_recent_news'].(user_can('newsmanage') ? " - <font class=\"small\">[<a class=\"altlink\" href=\"news.php\"><b>".$lang_index['text_news_page']."</b></a>]</font>" : "") ?></h2>
    <?php
    $latestNews = \App\Models\News::query()->orderByDesc('added')->limit((int)$maxnewsnum_main)->get()->toArray();
    if (count($latestNews) > 0) {
        echo '<div class="i2-news-list">';
        $news_flag = 0;
        foreach ($latestNews as $newsItem) {
            $id = (int)$newsItem['id'];
            $title = htmlspecialchars($newsItem['title']);
            $body = format_comment($newsItem["body"], 0);
            $date = date("Y.m.d", strtotime($newsItem['added']));
            $display = ($news_flag < 1) ? 'block' : 'none';
            $imgClass = ($news_flag < 1) ? 'minus' : 'plus';
            echo '<div class="i2-news-item">';
            echo '<a href="javascript: klappe_news(\'a'.$id.'\')"><img class="'.$imgClass.'" src="pic/trans.gif" id="pica'.$id.'" alt="Show/Hide" title="'.htmlspecialchars($lang_index['title_show_or_hide'] ?? 'Show/Hide').'" />&nbsp;'.$date.' - <b>'.$title.'</b></a>';
            echo '<div id="ka'.$id.'" style="display: '.$display.';"> '.$body.' </div>';
            if (user_can('newsmanage')) {
                echo '&nbsp; [<a class="faqlink" href="news.php?action=edit&amp;newsid='.$id.'"><b>'.htmlspecialchars($lang_index['text_e'] ?? 'E').'</b></a>]';
                echo ' [<a class="faqlink" href="news.php?action=delete&amp;newsid='.$id.'"><b>'.htmlspecialchars($lang_index['text_d'] ?? 'D').'</b></a>]';
            }
            echo '</div>';
            $news_flag++;
        }
        echo '</div>';
    }
    ?>
</section>
<?php // ------------- end: recent news ------------------// ?>

<?php // ------------- start: shoutbox ------------------// ?>
<?php if ($showshoutbox_main == "yes") { ?>
<section class="i2-section i2-shoutbox">
    <h2>
        <?php echo $lang_index['text_shoutbox'] ?> - <font class="small"><?php echo $lang_index['text_auto_refresh_after']?></font>
        <font class='striking' id="countdown"></font><font class="small"><?php echo $lang_index['text_seconds']?></font>
        - <a href="shoutbox_history.php" class="small"><?php echo $lang_index['text_shoutbox_history'] ?? 'History'; ?></a>
        <?php
        if (user_can('sbmanage')) {
            echo ' - <font class="small" id="clear-shout-box">[<a class="altlink" href="javascript:;"><b>'.$lang_index['clear_shout_box'].'</b></a>]</font>';
            $clearShoutBoxJs = <<<JS
jQuery('#clear-shout-box').on("click", function () {
    layer.confirm("{$lang_index['sure_to_clear_shout_box']}", {title: "Info", btn: ['Yes', "Cancel"], btnAlign: 'c'}, function (layerIndex) {
        jQuery.post("ajax.php", {"action": "clearShoutBox", "params": {"csrf": (typeof SHOUT_CSRF !== 'undefined' ? SHOUT_CSRF : '')}}, function (response) {
            layer.close(layerIndex)
            if (response.ret != 0) {
                layer.alert(response.msg, {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'})
            } else {
                document.getElementById('iframe-shout-box').src='shoutbox.php?type=shoutbox';
            }
        }, "json")
    })
})
JS;
            \Nexus\Nexus::js($clearShoutBoxJs, 'footer', false);
        }
        ?>
    </h2>
    <table width="100%"><tr><td class="text">
        <iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />
        <form action='shoutbox.php' method='get' target='sbox' name='shbox'>
            <?php print(\App\Support\Shoutbox::toolbar('shbox', 'shbox_text')); ?>
            <div style="display: flex">
                <label for='shbox_text'><?php echo $lang_index['text_message']?></label>
                <input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />
                <input type='submit' id='hbsubmit' class='btn' name='shout' value="<?php echo $lang_index['sumbit_shout']?>" />
                <input type='reset' class='btn' value="<?php echo $lang_index['submit_clear']?>" />
                <input type='hidden' name='sent' value='yes' />
                <input type='hidden' name='type' value='shoutbox' />
            </div>
        </form>
    </td></tr></table>
</section>
<?php } ?>
<?php // ------------- end: shoutbox ------------------// ?>

<?php
// ------------- start: extra modules ------------------//
$extraModules = [];
$extraModules = apply_filter('nexus_home_module', $extraModules);
print implode('', $extraModules);
// ------------- end: extra modules ------------------//
?>

<?php // ------------- start: latest forum posts ------------------// ?>
<?php if ($showlastxforumposts_main == "yes" && $CURUSER) { ?>
<section class="i2-section i2-forum-posts">
    <h2><?php echo $lang_index['text_last_five_posts'] ?></h2>
    <?php
    $latestPosts = \App\Models\Post::query()
        ->join('topics', 'posts.topicid', '=', 'topics.id')
        ->join('forums', 'topics.forumid', '=', 'forums.id')
        ->where('forums.minclassread', '<=', get_user_class())
        ->orderByDesc('posts.id')
        ->limit(5)
        ->get([
            'posts.id as pid',
            'posts.userid as userpost',
            'posts.added',
            'topics.id as tid',
            'topics.subject',
            'topics.forumid',
            'topics.views',
            'forums.name',
        ])
        ->toArray();
    if (count($latestPosts) > 0) {
        print("<table class=\"i2-table i2-forum-table\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\" align=\"left\">".$lang_index['col_topic_title']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_view']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"left\">".$lang_index['col_posted_at']."</td></tr>");
        foreach ($latestPosts as $postsx) {
            print("<tr><td><a href=\"forums.php?action=viewtopic&amp;topicid=".$postsx["tid"]."&amp;page=p".$postsx["pid"]."#pid".$postsx["pid"]."\"><b>".htmlspecialchars($postsx["subject"])."</b></a><br />".$lang_index['text_in']."<a href=\"forums.php?action=viewforum&amp;forumid=".$postsx["forumid"]."\">".htmlspecialchars($postsx["name"])."</a></td><td align=\"center\">".$postsx["views"]."</td><td align=\"center\">" . get_username($postsx["userpost"]) ."</td><td>".gettime($postsx["added"])."</td></tr>");
        }
        print("</table>");
    }
    ?>
</section>
<?php } ?>
<?php // ------------- end: latest forum posts ------------------// ?>

<?php // ------------- start: latest torrents ------------------// ?>
<section class="i2-section i2-torrents">
    <?php if ($showlastxtorrents_main == "yes") { ?>
        <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_last_five_torrent'] ?? 'Latest Torrents') ?></h2>
        <div class="i2-card-grid">
            <?php foreach ($latest as $torrent): $renderTorrentCard($torrent); endforeach; ?>
        </div>
    <?php } ?>

    <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_trending'] ?? 'Trending') ?></h2>
    <div class="i2-card-grid">
        <?php foreach ($trending as $torrent): $renderTorrentCard($torrent); endforeach; ?>
    </div>

    <h2 class="i2-card-grid-title"><?php echo htmlspecialchars($lang_index['text_most_snatched'] ?? 'Most Snatched') ?></h2>
    <div class="i2-card-grid">
        <?php foreach ($snatched as $torrent): $renderTorrentCard($torrent); endforeach; ?>
    </div>
</section>
<?php // ------------- end: latest torrents ------------------// ?>

<?php // ------------- start: top uploader ------------------// ?>
<?php if (get_setting('main.show_top_uploader') == "yes") { ?>
<section class="i2-section i2-top-uploaders">
    <?php
    $allUploaders = \App\Repositories\IndexRepository::getTopUploaders(10);
    if ($allUploaders->isNotEmpty()) {
        \Nexus\Nexus::css('.tr-top-uploader-tab>td {cursor: pointer}', 'footer', false);
        $toggleTimeRangeJs = <<<JS
jQuery(".tr-top-uploader-tab").on("click", "td", function () {
    let _this = jQuery(this)
    if (_this.hasClass("colhead")) {
        return
    }
    _this.parent().children().removeClass("colhead")
    _this.addClass("colhead")
    jQuery(".top-uploader").hide()
    jQuery("." + _this.attr("data-table")).fadeIn()
})
JS;
        \Nexus\Nexus::js($toggleTimeRangeJs, "footer", false);
        print ("<h2>".$lang_index['top_uploader_title']."</h2>");
        print("<table class=\"i2-top-uploader-tabs\" width='100%'><tr class='tr-top-uploader-tab' title='{$lang_index['top_uploader_toggle_time_range_tab']}'><td class='colhead' align='center' data-table='top-uploader-recently'>{$lang_index['top_uploader_toggle_time_range_recently']}</td><td align='center' data-table='top-uploader-all'>{$lang_index['top_uploader_toggle_time_range_all']}</td></tr></table>");

        $recentUploaders = \App\Repositories\IndexRepository::getTopUploaders(10, 30);
        $renderTopUploaderTable($allUploaders, 'top-uploader top-uploader-all', 'display: none', $lang_index);
        $renderTopUploaderTable($recentUploaders, 'top-uploader top-uploader-recently', '', $lang_index);
    }
    ?>
</section>
<?php } ?>
<?php // ------------- end: top uploader ------------------// ?>

<?php // ------------- start: polls ------------------// ?>
<?php if ($CURUSER && $showpolls_main == "yes") { ?>
<section class="i2-section i2-poll">
    <?php
    $pollArr = $Cache->get_value('current_poll_content');
    if ($pollArr === false || $pollArr === null) {
        $pollArr = \App\Repositories\IndexRepository::getCurrentPoll();
        if ($pollArr) {
            $Cache->cache_value('current_poll_content', $pollArr, 7226);
        }
    }
    $pollexists = !empty($pollArr);

    print("<h2>".$lang_index['text_polls']);

    if (user_can('pollmanage')) {
        print("<font class=\"small\"> - [<a class=\"altlink\" href=\"makepoll.php?returnto=main\"><b>".$lang_index['text_new']."</b></a>]\n");
        if ($pollexists) {
            print(" - [<a class=\"altlink\" href=\"makepoll.php?action=edit&amp;pollid=".$pollArr['id']."&amp;returnto=main\"><b>".$lang_index['text_edit']."</b></a>]\n");
            print(" - [<a class=\"altlink\" href=\"log.php?action=poll&amp;do=delete&amp;pollid=".$pollArr['id']."&amp;returnto=main\"><b>".$lang_index['text_delete']."</b></a>]");
            print(" - [<a class=\"altlink\" href=\"polloverview.php?id=".$pollArr['id']."\"><b>".$lang_index['text_detail']."</b></a>]");
        }
        print("</font>");
    }
    print("</h2>");
    if ($pollexists) {
        $pollid = intval($pollArr["id"] ?? 0);
        $question = htmlspecialchars($pollArr["question"]);
        $o = [];
        for ($i = 0; $i <= \App\Models\Poll::MAX_OPTION_INDEX; ++$i) {
            $o[$i] = $pollArr["option{$i}"] ?? '';
        }

        print("<div class=\"i2-poll-content\">");
        print("<p><b>".$question."</b></p>");

        $uservote = \App\Repositories\IndexRepository::getUserVote($pollid, $CURUSER["id"]);
        if ($uservote !== null) {
            $results = $Cache->get_value('current_poll_result');
            if ($results === false || $results === null) {
                $results = \App\Repositories\IndexRepository::getPollResults($pollid);
                $Cache->cache_value('current_poll_result', $results, 3652);
            }
            $tvotes = array_sum(array_column($results, 'count'));

            print("<table class=\"i2-poll-results\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n");
            foreach ($results as $item) {
                if ($tvotes == 0)
                    $p = 0;
                else
                    $p = round($item['count'] / $tvotes * 100);
                $barClass = ($item['index'] == $uservote) ? 'sltbar' : 'unsltbar';
                print("<tr><td width=\"1%\" class=\"embedded nowrap\">" . htmlspecialchars($item['option']) . "&nbsp;&nbsp;</td><td width=\"99%\" class=\"embedded nowrap\"><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /><img class=\"" . $barClass . "\" src=\"pic/trans.gif\" style=\"width: " . ($p * 3) ."px;\" alt=\"\" /> $p%</td></tr>\n");
            }
            print("</table>\n");
            $tvotes = number_format($tvotes);
            print("<p>".$lang_index['text_votes']." ".$tvotes."</p>\n");

            if (user_can('log'))
                print("<p><a href=\"log.php?action=poll\">".$lang_index['text_previous_polls']."</a></p>\n");
        } else {
            print("<form method=\"post\" action=\"index2.php\">\n");
            for ($i = 0; $i < count($o); ++$i) {
                if ($o[$i])
                    print("<label><input type=\"radio\" name=\"choice\" value=\"".$i."\"> ".htmlspecialchars($o[$i])."</label><br />\n");
            }
            print("<label><input type=\"radio\" name=\"choice\" value=\"255\"> ".$lang_index['radio_blank_vote']."</label><br />\n");
            print("<p><input type=\"submit\" class=\"btn\" value=\"".$lang_index['submit_vote']."\" /></p>");
            print("</form>");
        }
        print("</div>");
    }
    ?>
</section>
<?php } ?>
<?php // ------------- end: polls ------------------// ?>

<?php // ------------- start: stats ------------------// ?>
<?php if ($showstats_main == "yes") { ?>
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

    <div class="i2-stats-tables">
        <h3><?php echo htmlspecialchars($lang_index['text_tracker_statistics'] ?? 'Tracker Statistics') ?></h3>
        <table class="i2-table i2-stats-table" width="100%" border="1" cellspacing="0" cellpadding="10">
            <tr><?php twotd($lang_index['row_users_active_today'], number_format($userStats['totalonlinetoday'])) ?></tr>
            <tr><?php twotd($lang_index['row_users_active_this_week'], number_format($userStats['totalonlineweek'])) ?></tr>
            <tr><?php twotd($lang_index['row_registered_users'], number_format($userStats['registered'])." / ".number_format($maxusers)) ?></tr>
            <tr><?php twotd($lang_index['row_unconfirmed_users'], number_format($userStats['unverified'])) ?></tr>
            <tr><?php twotd(get_user_class_name(UC_VIP,false,false,true), number_format($userStats['vip'])) ?></tr>
            <tr><?php twotd($lang_index['row_donors']." <img class=\"star\" src=\"pic/trans.gif\" alt=\"Donor\" />", number_format($userStats['donated'])) ?></tr>
            <tr><?php twotd($lang_index['row_warned_users']." <img class=\"warned\" src=\"pic/trans.gif\" alt=\"warned\" />", number_format($userStats['warned'])) ?></tr>
            <tr><?php twotd($lang_index['row_banned_users']." <img class=\"disabled\" src=\"pic/trans.gif\" alt=\"disabled\" />", number_format($userStats['disabled'])) ?></tr>
            <tr><?php twotd($lang_index['row_male_users'], number_format($userStats['registered_male'])) ?></tr>
            <tr><?php twotd($lang_index['row_female_users'], number_format($userStats['registered_female'])) ?></tr>
        </table>

        <table class="i2-table i2-stats-table" width="100%" border="1" cellspacing="0" cellpadding="10">
            <tr><?php twotd($lang_index['row_torrents'], number_format($torrentStats['torrents'])) ?></tr>
            <tr><?php twotd($lang_index['row_dead_torrents'], number_format($torrentStats['dead'])) ?></tr>
            <tr><?php twotd($lang_index['row_seeders'], number_format($torrentStats['seeders'])) ?></tr>
            <tr><?php twotd($lang_index['row_leechers'], number_format($torrentStats['leechers'])) ?></tr>
            <tr><?php twotd($lang_index['row_peers'], number_format($torrentStats['peers'])) ?></tr>
            <tr><?php twotd($lang_index['row_seeder_leecher_ratio'], $torrentStats['ratio']."%") ?></tr>
            <tr><?php twotd($lang_index['row_active_browsing_users'], number_format($torrentStats['activewebusernow'])) ?></tr>
            <tr><?php twotd($lang_index['row_tracker_active_users'], number_format($torrentStats['activetrackerusernow'])) ?></tr>
            <tr><?php twotd($lang_index['row_total_size_of_torrents'], mksize($torrentStats['totaltorrentssize'])) ?></tr>
            <tr><?php twotd($lang_index['row_total_uploaded'], mksize($torrentStats['totaluploaded'])) ?></tr>
            <tr><?php twotd($lang_index['row_total_downloaded'], mksize($torrentStats['totaldownloaded'])) ?></tr>
            <tr><?php twotd($lang_index['row_total_data'], mksize($torrentStats['totaldata'])) ?></tr>
        </table>

        <table class="i2-table i2-class-table" width="100%" border="1" cellspacing="0" cellpadding="10">
            <?php
            $classPairs = array_chunk($classStats, 2, true);
            foreach ($classPairs as $pair) {
                echo '<tr>';
                foreach ($pair as $class => $count) {
                    twotd(get_user_class_name((int)$class, false, false, true), number_format($count));
                }
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</section>
<?php } ?>
<?php // ------------- end: stats ------------------// ?>

<?php // ------------- start: tracker load ------------------// ?>
<?php if ($showtrackerload == "yes") { ?>
<section class="i2-section i2-tracker-load">
    <h2><?php echo htmlspecialchars($lang_index['text_tracker_load'] ?? 'Tracker Load') ?></h2>
    <?php
    $uptimeresult = exec('uptime');
    if ($uptimeresult) {
        print ('<div class="i2-tracker-load-box">'.htmlspecialchars(trim($uptimeresult)).'</div>');
    }
    ?>
</section>
<?php } ?>
<?php // ------------- end: tracker load ------------------// ?>

<?php // ------------- start: disclaimer ------------------// ?>
<section class="i2-section i2-disclaimer">
    <h2><?php echo htmlspecialchars($lang_index['text_disclaimer'] ?? 'Disclaimer') ?></h2>
    <div><?php echo sprintf($lang_index['text_disclaimer_content'] ?? '%s', \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName()) ?></div>
</section>
<?php // ------------- end: disclaimer ------------------// ?>

<?php // ------------- start: browser, client and code note ------------------// ?>
<section class="i2-section i2-browser-note">
    <table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
        <div align="center"><br /><font class="medium"><?php echo $lang_index['text_browser_note'] ?? '' ?></font></div>
        <div align="center"><a href="<?php echo NEXUSPHPURL?>" title="<?php echo PROJECTNAME?>" target="_blank"><img src="pic/nexus.png" alt="<?php echo PROJECTNAME?>" /></a></div>
    </td></tr></table>
</section>
<?php // ------------- end: browser, client and code note ------------------// ?>

</div>

<?php
\App\Models\User::where('id', $userid)->update(['last_home' => now()]);
$Cache->delete_value('user_'.$userid.'_unread_news_count');
end_main_frame();
stdfoot();
?>
