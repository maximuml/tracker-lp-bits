<?php

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Poll;
use App\Models\Setting;
use App\Repositories\IndexRepository;
use App\Support\Config\SiteConfig;
use App\Support\CoverThumb;
use App\Support\Format;
use App\Support\Hooks;
use App\Support\Html;
use App\Support\Shoutbox;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Nexus\Nexus;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($Cache)) {
    $Cache = SupportContext::getCache();
}
if (! isset($lang_index)) {
    $lang_index = (array) (SupportContext::getGlobal('lang_index') ?? []);
}
// ------------- start: recent news ------------------//
echo '<h2>'.$lang_index['text_recent_news'].(Permission::can(PermissionEnum::NEWS_MANAGE) ? ' - <font class="small">[<a class="altlink" href="news.php"><b>'.$lang_index['text_news_page'].'</b></a>]</font>' : '').'</h2>';

$Cache->new_page('recent_news', 86400, true);
if (! $Cache->get_page()) {
    $latestNews = IndexRepository::getLatestNews((int) $maxnewsnum_main);
    if (count($latestNews) > 0) {
        $Cache->add_whole_row();
        echo "<table width=\"100%\"><tr><td class=\"text\"><div style=\"margin-left: 16pt;\">\n";
        $Cache->end_whole_row();
        $news_flag = 0;
        foreach ($latestNews as $newsItem) {
            $Cache->add_row();
            $Cache->add_part();
            if ($news_flag < 1) {
                echo "<a href=\"javascript: klappe_news('a".$newsItem['id']."')\"><img class=\"minus\" src=\"pic/trans.gif\" id=\"pica".$newsItem['id'].'" alt="Show/Hide" title="'.$lang_index['title_show_or_hide'].'" />&nbsp;'.date('Y.m.d', strtotime($newsItem['added'])).' - '.'<b>'.$newsItem['title'].'</b></a>';
                echo '<div id="ka'.$newsItem['id'].'" style="display: block;"> '.Format::formatComment($newsItem['body'], 0).' </div> ';
                $news_flag = $news_flag + 1;
            } else {
                echo "<a href=\"javascript: klappe_news('a".$newsItem['id']."')\"><br /><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica".$newsItem['id'].'" alt="Show/Hide" title="'.$lang_index['title_show_or_hide'].'" />&nbsp;'.date('Y.m.d', strtotime($newsItem['added'])).' - '.'<b>'.$newsItem['title'].'</b></a>';
                echo '<div id="ka'.$newsItem['id'].'" style="display: none;"> '.Format::formatComment($newsItem['body'], 0).' </div> ';
            }
            $Cache->end_part();
            $Cache->add_part();
            echo '  &nbsp; [<a class="faqlink" href="news.php?action=edit&amp;newsid='.$newsItem['id'].'"><b>'.$lang_index['text_e'].'</b></a>]';
            echo ' [<a class="faqlink" href="news.php?action=delete&amp;newsid='.$newsItem['id'].'"><b>'.$lang_index['text_d'].'</b></a>]';
            $Cache->end_part();
            $Cache->end_row();
        }
        $Cache->break_loop();
        $Cache->add_whole_row();
        echo "</div></td></tr></table>\n";
        $Cache->end_whole_row();
    }
    $Cache->cache_page();
}
echo $Cache->next_row();
while ($Cache->next_row()) {
    echo $Cache->next_part();
    if (Permission::can(PermissionEnum::NEWS_MANAGE)) {
        echo $Cache->next_part();
    }
}
echo $Cache->next_row();
// ------------- end: recent news ------------------//
// ------------- start: hot and classic movies ------------------//
// displayHotAndClassic();
// ------------- end: hot and classic movies ------------------//
// ------------- start: shoutbox ------------------//
if ($showshoutbox_main == 'yes') {
    Nexus::js("var SHOUT_CSRF = '".addslashes(Shoutbox::csrfToken((int) ($CURUSER['id'] ?? 0)))."';", 'footer', false);
    ?>
    <h2>
        <?php echo $lang_index['text_shoutbox'] ?> - <font class="small"><?php echo $lang_index['text_auto_refresh_after']?></font>
        <font class='striking' id="countdown"></font><font class="small"><?php echo $lang_index['text_seconds']?></font>
        - <a href="shoutbox_history.php" class="small"><?php echo $lang_index['text_shoutbox_history'] ?? 'History'; ?></a>
        <?php
            if (Permission::can(PermissionEnum::SB_MANAGE)) {
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
                Nexus::js($clearShoutBoxJs, 'footer', false);
            }
    ?>
    </h2>
<?php
    echo "<table width=\"100%\"><tr><td class=\"text\">\n";
    echo "<iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n";
    echo "<form action='shoutbox.php' method='get' target='sbox' name='shbox'>\n";
    echo Shoutbox::toolbar('shbox', 'shbox_text');
    echo '<div style="display: flex">';
    echo "<label for='shbox_text'>".$lang_index['text_message']."</label><input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />  <input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_index['sumbit_shout'].'" />';
    echo "<input type='reset' class='btn' value=\"".$lang_index['submit_clear']."\" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />";
    echo '</div>';
    echo '</form></td></tr></table>';
}
// ------------- end: shoutbox ------------------//

$extraModules = [];
$extraModules = Hooks::applyFilter('nexus_home_module', ...[$extraModules]);
echo implode('', $extraModules);

// ------------- start: latest forum posts ------------------//

if ($showlastxforumposts_main == 'yes' && $CURUSER) {
    $latestPosts = IndexRepository::getLatestForumPosts(5, (int) UserDisplay::currentClass());
    if (count($latestPosts) > 0) {
        echo '<h2>'.$lang_index['text_last_five_posts'].'</h2>';
        echo '<table width="100%" border="1" cellspacing="0" cellpadding="5"><tr><td class="colhead" width="100%" align="left">'.$lang_index['col_topic_title'].'</td><td class="colhead" align="center">'.$lang_index['col_view'].'</td><td class="colhead" align="center">'.$lang_index['col_author'].'</td><td class="colhead" align="left">'.$lang_index['col_posted_at'].'</td></tr>';

        foreach ($latestPosts as $postsx) {
            echo '<tr><td><a href="forums.php?action=viewtopic&amp;topicid='.$postsx['tid'].'&amp;page=p'.$postsx['pid'].'#pid'.$postsx['pid'].'"><b>'.htmlspecialchars($postsx['subject']).'</b></a><br />'.$lang_index['text_in'].'<a href="forums.php?action=viewforum&amp;forumid='.$postsx['forumid'].'">'.htmlspecialchars($postsx['name']).'</a></td><td align="center">'.$postsx['views'].'</td><td align="center">'.UserDisplay::username($postsx['userpost']).'</td><td>'.Time::format($postsx['added']).'</td></tr>';
        }
        echo '</table>';
    }
}

// ------------- end: latest forum posts ------------------//
// ------------- start: latest torrents ------------------//

if ($showlastxtorrents_main == 'yes') {
    $ltCacheKey = 'index_latest_torrents_grid_v2';
    $ltCacheTtl = 120;
    $ltHtml = $Cache->get_value($ltCacheKey);
    if ($ltHtml === false || $ltHtml === null || $ltHtml === '') {
        $latestTorrents = IndexRepository::getLatestTorrents(9);
        if ($latestTorrents->isNotEmpty()) {
            ob_start();
            ?>
				<h2><?php echo $lang_index['text_last_five_torrent'] ?></h2>
				<style>
					.lt-grid {
						display: grid;
						grid-template-columns: repeat(3, 1fr);
						gap: 12px;
						margin: 8px 0 16px;
					}
					.lt-grid .lt-card {
						border: 1px solid rgba(127,127,127,.35);
						border-radius: 6px;
						overflow: hidden;
						display: flex;
						flex-direction: column;
						background: rgba(127,127,127,.05);
					}
					.lt-grid .lt-cover {
						position: relative;
						display: block;
						width: 100%;
						aspect-ratio: 2 / 3;
						max-height: 240px;
						background: rgba(0,0,0,.08);
						overflow: hidden;
					}
					.lt-grid .lt-cover img,
					.lt-grid .lt-cover .lt-cover-fallback {
						width: 100%;
						height: 100%;
						object-fit: cover;
						display: block;
					}
					.lt-grid .lt-cover-fallback {
						display: flex;
						align-items: center;
						justify-content: center;
						padding: 8px;
						box-sizing: border-box;
						font-size: 12px;
						line-height: 1.3;
						color: rgba(127,127,127,.85);
						text-align: center;
						word-break: break-word;
					}
					.lt-grid .lt-type {
						position: absolute;
						top: 6px;
						right: 6px;
						background: rgba(0,0,0,.78);
						color: #fff;
						font-size: 11px;
						font-weight: bold;
						padding: 2px 6px;
						border-radius: 3px;
						line-height: 1.2;
						letter-spacing: .3px;
						pointer-events: none;
					}
					.lt-grid .lt-title {
						padding: 6px 8px 4px;
						font-size: 12px;
						line-height: 1.3;
						overflow: hidden;
						display: -webkit-box;
						-webkit-line-clamp: 2;
						-webkit-box-orient: vertical;
					}
					.lt-grid .lt-title a { text-decoration: none; }
					.lt-grid .lt-meta {
						margin-top: auto;
						display: flex;
						flex-wrap: wrap;
						gap: 4px 10px;
						padding: 6px 8px;
						font-size: 11px;
						border-top: 1px solid rgba(127,127,127,.2);
					}
					.lt-grid .lt-seed { color: #2fad2f; font-weight: bold; }
					.lt-grid .lt-leech { color: #d04848; font-weight: bold; }
					@media (max-width: 700px) {
						.lt-grid { grid-template-columns: repeat(2, 1fr); }
					}
					@media (max-width: 420px) {
						.lt-grid { grid-template-columns: 1fr; }
					}
				</style>
				<div class="lt-grid">
				<?php
            foreach ($latestTorrents as $torrent) {
                $detailsUrl = 'details.php?id='.(int) $torrent->id.'&hit=1';
                $rawCover = trim((string) ($torrent->cover ?? ''));
                $thumbUrl = $rawCover !== '' ? CoverThumb::urlWithContext((string) $rawCover, (int) 240, (int) 360, (int) 82) : '';
                $typeLabel = trim((string) ($torrent->basic_category->name ?? ''));
                if (($torrent->anonymous ?? 'no') === 'yes') {
                    $ownerHtml = '<i>Anonymous</i>';
                } else {
                    $ownerHtml = UserDisplay::username((int) $torrent->owner);
                }
                $nameSafe = htmlspecialchars($torrent->name);
                ?>
					<div class="lt-card">
						<a class="lt-cover" href="<?php echo htmlspecialchars($detailsUrl) ?>" title="<?php echo $nameSafe ?>">
							<?php if ($thumbUrl !== '') { ?>
								<img src="<?php echo htmlspecialchars($thumbUrl) ?>" alt="<?php echo $nameSafe ?>" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.style.display='flex';}" />
								<div class="lt-cover-fallback" style="display:none;"><?php echo htmlspecialchars(mb_substr($torrent->name, 0, 60)) ?></div>
							<?php } else { ?>
								<div class="lt-cover-fallback"><?php echo htmlspecialchars(mb_substr($torrent->name, 0, 60)) ?></div>
							<?php } ?>
							<?php if ($typeLabel !== '') { ?>
								<span class="lt-type"><?php echo htmlspecialchars($typeLabel) ?></span>
							<?php } ?>
						</a>
						<div class="lt-title">
							<a href="<?php echo htmlspecialchars($detailsUrl) ?>"><b><?php echo $nameSafe ?></b></a>
						</div>
						<div class="lt-meta">
							<span class="lt-seed" title="<?php echo htmlspecialchars($lang_index['col_seeder']) ?>">&#x25B2; <?php echo (int) $torrent->seeders ?></span>
							<span class="lt-leech" title="<?php echo htmlspecialchars($lang_index['col_leecher']) ?>">&#x25BC; <?php echo (int) $torrent->leechers ?></span>
							<span><?php echo Format::size((int) $torrent->size) ?></span>
							<span><?php echo $ownerHtml ?></span>
						</div>
					</div>
					<?php
            }
            ?>
				</div>
				<?php
            $ltHtml = ob_get_clean();
            $Cache->cache_value($ltCacheKey, $ltHtml, $ltCacheTtl);
        } else {
            $ltHtml = '';
            $Cache->cache_value($ltCacheKey, $ltHtml, $ltCacheTtl);
        }
    }
    echo $ltHtml;
}
// ------------- end: latest torrents ------------------//

// ------------- start: top uploader ------------------//

if (SiteConfig::current()->main->showTopUploader()) {
    $allUploaders = IndexRepository::getTopUploaders(10);
    if ($allUploaders->isNotEmpty()) {
        Nexus::css('.tr-top-uploader-tab>td {cursor: pointer}', 'footer', false);
        $toggleTimeRangeJs = <<<'JS'
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
        Nexus::js($toggleTimeRangeJs, 'footer', false);
        echo '<h2>'.$lang_index['top_uploader_title'].'</h2>';
        echo "<table width='100%'><tr class='tr-top-uploader-tab' title='{$lang_index['top_uploader_toggle_time_range_tab']}'><td class='colhead' align='center' data-table='top-uploader-recently'>{$lang_index['top_uploader_toggle_time_range_recently']}</td><td align='center' data-table='top-uploader-all'>{$lang_index['top_uploader_toggle_time_range_all']}</td></tr></table>";

        $recentUploaders = IndexRepository::getTopUploaders(10, 30);
        echo "<table class='top-uploader top-uploader-all' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" style='display: none'><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author'].'</td><td class="colhead" align="center">'.$lang_index['col_counts'].'</td><td class="colhead" align="center">'.$lang_index['col_ranking'].'</td></tr>';
        foreach ($allUploaders as $ranking => $uploader) {
            echo '<tr><td>'.UserDisplay::username($uploader->id).'</td><td align="center">'.$uploader->count.'</td><td align="center">'.($ranking + 1).'</td></tr>';
        }
        echo '</table>';

        echo "<table class='top-uploader top-uploader-recently' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author'].'</td><td class="colhead" align="center">'.$lang_index['col_counts'].'</td><td class="colhead" align="center">'.$lang_index['col_ranking'].'</td></tr>';
        foreach ($recentUploaders as $ranking => $uploader) {
            echo '<tr><td>'.UserDisplay::username($uploader->id).'</td><td align="center">'.$uploader->count.'</td><td align="center">'.($ranking + 1).'</td></tr>';
        }
        echo '</table>';
    }
}
// ------------- end: top uploader ------------------//

// ------------- start: polls ------------------//
if ($CURUSER && $showpolls_main == 'yes') {
    $pollArr = $Cache->get_value('current_poll_content');
    if ($pollArr === false || $pollArr === null) {
        $pollArr = IndexRepository::getCurrentPoll();
        if ($pollArr) {
            $Cache->cache_value('current_poll_content', $pollArr, 7226);
        }
    }
    $pollexists = ! empty($pollArr);

    echo '<h2>'.$lang_index['text_polls'];

    if (Permission::can(PermissionEnum::POLL_MANAGE)) {
        echo '<font class="small"> - [<a class="altlink" href="makepoll.php?returnto=main"><b>'.$lang_index['text_new']."</b></a>]\n";
        if ($pollexists) {
            echo ' - [<a class="altlink" href="makepoll.php?action=edit&amp;pollid='.$pollArr['id'].'&amp;returnto=main"><b>'.$lang_index['text_edit']."</b></a>]\n";
            echo ' - [<a class="altlink" href="log.php?action=poll&amp;do=delete&amp;pollid='.$pollArr['id'].'&amp;returnto=main"><b>'.$lang_index['text_delete'].'</b></a>]';
            echo ' - [<a class="altlink" href="polloverview.php?id='.$pollArr['id'].'"><b>'.$lang_index['text_detail'].'</b></a>]';
        }
        echo '</font>';
    }
    echo '</h2>';
    if ($pollexists) {
        $pollid = intval($pollArr['id'] ?? 0);
        $question = $pollArr['question'];
        $o = [];
        for ($i = 0; $i <= Poll::MAX_OPTION_INDEX; $i++) {
            $o[$i] = $pollArr["option{$i}"] ?? '';
        }

        echo "<table width=\"100%\"><tr><td class=\"text\" align=\"center\">\n";
        echo '<table width="59%" class="main" border="1" cellspacing="0" cellpadding="5"><tr><td class="text" align="left">';
        echo '<p align="center"><b>'.$question."</b></p>\n";

        $uservote = IndexRepository::getUserVote($pollid, $CURUSER['id']);
        if ($uservote !== null) { // user has already voted
            $results = $Cache->get_value('current_poll_result');
            if ($results === false || $results === null) {
                $results = IndexRepository::getPollResults($pollid);
                $Cache->cache_value('current_poll_result', $results, 3652);
            }
            $tvotes = array_sum(array_column($results, 'count'));

            echo "<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
            foreach ($results as $item) {
                if ($tvotes == 0) {
                    $p = 0;
                } else {
                    $p = round($item['count'] / $tvotes * 100);
                }
                $barClass = ($item['index'] == $uservote) ? 'sltbar' : 'unsltbar';
                echo '<tr><td width="1%" class="embedded nowrap">'.$item['option'].'&nbsp;&nbsp;</td><td width="99%" class="embedded nowrap"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="'.$barClass.'" src="pic/trans.gif" style="width: '.($p * 3)."px;\" alt=\"\" /><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /> $p%</td></tr>\n";
            }
            echo "</table>\n";
            $tvotes = number_format($tvotes);
            echo '<p align="center">'.$lang_index['text_votes'].' '.$tvotes."</p>\n";

            if (Permission::can(PermissionEnum::LOG)) {
                echo '<p align="center"><a href="log.php?action=poll">'.$lang_index['text_previous_polls']."</a></p>\n";
            }
        } else { // user has not voted yet
            echo "<form method=\"post\" action=\"index.php\">\n";
            echo '<input type="hidden" name="_token" value="'.htmlspecialchars(csrf_token())."\" />\n";
            for ($i = 0; $i < count($o); $i++) {
                if ($o[$i]) {
                    echo '<input type="radio" name="choice" value="'.$i.'">'.$o[$i]."<br />\n";
                }
            }
            echo '<br />';
            echo '<input type="radio" name="choice" value="255">'.$lang_index['radio_blank_vote']."<br />\n";
            echo '<p align="center"><input type="submit" class="btn" value="'.$lang_index['submit_vote'].'" /></p>';
        }
        echo '</td></tr></table>';
        echo '</td></tr></table>';
    }
}
// ------------- end: polls ------------------//

// ------------- start: stats ------------------//
if ($showstats_main == 'yes') {
    ?>
<h2><?php echo $lang_index['text_tracker_statistics'] ?></h2>
<table width="100%"><tr><td class="text" align="center">
<table width="60%" class="main" border="1" cellspacing="0" cellpadding="10">
<?php
        $Cache->new_page('stats_users', 3000, true);
    if (! $Cache->get_page()) {
        $Cache->add_whole_row();
        $userStats = IndexRepository::getUserStats();
        $registered = number_format($userStats['registered']);
        $unverified = number_format($userStats['unverified']);
        $totalonlinetoday = number_format($userStats['totalonlinetoday']);
        $totalonlineweek = number_format($userStats['totalonlineweek']);
        $VIP = number_format($userStats['vip']);
        $donated = number_format($userStats['donated']);
        $warned = number_format($userStats['warned']);
        $disabled = number_format($userStats['disabled']);
        $registered_male = number_format($userStats['registered_male']);
        $registered_female = number_format($userStats['registered_female']);
        ?>
<tr>
<?php
            Html::twoTd($lang_index['row_users_active_today'], $totalonlinetoday);
        Html::twoTd($lang_index['row_users_active_this_week'], $totalonlineweek);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_registered_users'], $registered.' / '.number_format($maxusers));
        Html::twoTd($lang_index['row_unconfirmed_users'], $unverified);
        ?>
</tr>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_VIP, false, false, true), $VIP);
        Html::twoTd($lang_index['row_donors'].' <img class="star" src="pic/trans.gif" alt="Donor" />', $donated);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_warned_users'].' <img class="warned" src="pic/trans.gif" alt="warned" />', $warned);
        Html::twoTd($lang_index['row_banned_users'].' <img class="disabled" src="pic/trans.gif" alt="disabled" />', $disabled);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_male_users'], $registered_male);
        Html::twoTd($lang_index['row_female_users'], $registered_female);
        ?>
</tr>
<?php
            $Cache->end_whole_row();
        $Cache->cache_page();
    }
    echo $Cache->next_row();
    ?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
        $Cache->new_page('stats_torrents', 1800, true);
    if (! $Cache->get_page()) {
        $Cache->add_whole_row();
        $torrentStats = IndexRepository::getTorrentStats();
        $torrents = number_format($torrentStats['torrents']);
        $dead = number_format($torrentStats['dead']);
        $seeders = $torrentStats['seeders'];
        $leechers = $torrentStats['leechers'];
        $ratio = $torrentStats['ratio'];
        $activewebusernow = number_format($torrentStats['activewebusernow']);
        $activetrackerusernow = number_format($torrentStats['activetrackerusernow']);
        $peers = number_format($torrentStats['peers']);
        $seeders = number_format($seeders);
        $leechers = number_format($leechers);
        $totaltorrentssize = Format::size($torrentStats['totaltorrentssize']);
        $totaluploaded = $torrentStats['totaluploaded'];
        $totaldownloaded = $torrentStats['totaldownloaded'];
        $totaldata = $torrentStats['totaldata'];
        ?>
<tr>
<?php
            Html::twoTd($lang_index['row_torrents'], $torrents);
        Html::twoTd($lang_index['row_dead_torrents'], $dead);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_seeders'], $seeders);
        Html::twoTd($lang_index['row_leechers'], $leechers);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_peers'], $peers);
        Html::twoTd($lang_index['row_seeder_leecher_ratio'], $ratio.'%');
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_active_browsing_users'], $activewebusernow);
        Html::twoTd($lang_index['row_tracker_active_users'], $activetrackerusernow);
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_total_size_of_torrents'], $totaltorrentssize);
        Html::twoTd($lang_index['row_total_uploaded'], Format::size($totaluploaded));
        ?>
</tr>
<tr>
<?php
            Html::twoTd($lang_index['row_total_downloaded'], Format::size($totaldownloaded));
        Html::twoTd($lang_index['row_total_data'], Format::size($totaldata));
        ?>
</tr>
<?php
            $Cache->end_whole_row();
        $Cache->cache_page();
    }
    echo $Cache->next_row();
    ?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
        $Cache->new_page('stats_classes', 4535, true);
    if (! $Cache->get_page()) {
        $Cache->add_whole_row();
        $classStats = IndexRepository::getClassStats();
        $peasants = number_format($classStats[UC_PEASANT]);
        $users = number_format($classStats[UC_USER]);
        $powerusers = number_format($classStats[UC_POWER_USER]);
        $eliteusers = number_format($classStats[UC_ELITE_USER]);
        $crazyusers = number_format($classStats[UC_CRAZY_USER]);
        $insaneusers = number_format($classStats[UC_INSANE_USER]);
        $veteranusers = number_format($classStats[UC_VETERAN_USER]);
        $extremeusers = number_format($classStats[UC_EXTREME_USER]);
        $ultimateusers = number_format($classStats[UC_ULTIMATE_USER]);
        $nexusmasters = number_format($classStats[UC_NEXUS_MASTER]);
        ?>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_PEASANT, false, false, true).' <img class="leechwarned" src="pic/trans.gif" alt="leechwarned" />', $peasants);
        Html::twoTd(UserClass::name(UC_USER, false, false, true), $users);
        ?>
</tr>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_POWER_USER, false, false, true), $powerusers);
        Html::twoTd(UserClass::name(UC_ELITE_USER, false, false, true), $eliteusers);
        ?>
</tr>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_CRAZY_USER, false, false, true), $crazyusers);
        Html::twoTd(UserClass::name(UC_INSANE_USER, false, false, true), $insaneusers);
        ?>
</tr>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_VETERAN_USER, false, false, true), $veteranusers);
        Html::twoTd(UserClass::name(UC_EXTREME_USER, false, false, true), $extremeusers);
        ?>
</tr>
<tr>
<?php
            Html::twoTd(UserClass::name(UC_ULTIMATE_USER, false, false, true), $ultimateusers);
        Html::twoTd(UserClass::name(UC_NEXUS_MASTER, false, false, true), $nexusmasters);
        ?>
</tr>
<?php
            $Cache->end_whole_row();
        $Cache->cache_page();
    }
    echo $Cache->next_row();
    ?>
</table>
</td></tr></table>
<?php
}
// ------------- end: stats ------------------//

// ------------- start: tracker load ------------------//
if ($showtrackerload == 'yes') {
    $uptimeresult = exec('uptime');
    if ($uptimeresult) {
        ?>
<h2><?php echo $lang_index['text_tracker_load'] ?></h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text" align="center">
<?php
            // uptime, work in *nix system
            echo '<div align="center">'.trim($uptimeresult).'</div>';
        echo '</td></tr></table>';
    }
}
// ------------- end: tracker load ------------------//

// ------------- start: disclaimer ------------------//
?>
<h2><?php echo $lang_index['text_disclaimer'] ?></h2>
<table width="100%"><tr><td class="text">
  <?php echo sprintf($lang_index['text_disclaimer_content'], Setting::getSiteName(), Setting::getSiteName()) ?></td></tr></table>
<?php
// ------------- end: disclaimer ------------------//
// ------------- start: browser, client and code note ------------------//
?>
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<div align="center"><br /><font class="medium"><?php echo $lang_index['text_browser_note'] ?></font></div>
<div align="center"><a href="<?php echo NEXUSPHPURL?>" title="<?php echo PROJECTNAME?>" target="_blank"><img src="pic/nexus.png" alt="<?php echo PROJECTNAME?>" /></a></div>
</td></tr></table>
<?php
// ------------- end: browser, client and code note ------------------//
if ($CURUSER) {
    $Cache->delete_value('user_'.$CURUSER['id'].'_unread_news_count');
}
?>