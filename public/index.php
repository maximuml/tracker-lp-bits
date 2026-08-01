<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path());
loggedinorreturn(true);
$userid = $CURUSER["id"];
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($showpolls_main == "yes")
	{
		$choice = $_POST["choice"];
		if ($CURUSER && $choice != "" && $choice < 256 && $choice == floor($choice))
		{
			$poll = \App\Repositories\IndexRepository::getCurrentPoll();
			if (!$poll)
				die($lang_index['std_no_poll']);
			$pollid = $poll["id"];

			if (\App\Repositories\IndexRepository::hasVoted($pollid, $CURUSER["id"]))
				stderr($lang_index['std_error'],$lang_index['std_duplicate_votes_denied']);
			if (!\App\Repositories\IndexRepository::recordPollVote($pollid, $CURUSER["id"], (int)$choice))
				stderr($lang_index['std_error'], $lang_index['std_vote_not_counted']);
			$Cache->delete_value('current_poll_content');
			$Cache->delete_value('current_poll_result', true);
			//add karma
			KPS("+",$pollvote_bonus,$userid);

			header("Location: " . get_protocol_prefix() . "$BASEURL/");
			die;
		}
		else
		stderr($lang_index['std_error'], $lang_index['std_option_unselected']);
	}
}
\Nexus\Nexus::css('styles/shoutbox.css', 'header', true);
\Nexus\Nexus::js('js/shoutbox.js', 'footer', true);
$toastLang = json_encode([
    'newMessage' => $lang_index['toast_new_message'] ?? 'New message',
    'shoutboxMention' => $lang_index['toast_shoutbox_mention'] ?? 'Shoutbox mention',
    'from' => $lang_index['toast_from'] ?? 'From',
    'close' => $lang_index['toast_close'] ?? 'Close',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
\Nexus\Nexus::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
\Nexus\Nexus::css('styles/toast.css', 'header', true);
\Nexus\Nexus::js('js/toast.js', 'footer', true);
stdhead($lang_index['head_home']);
begin_main_frame();

// ------------- start: recent news ------------------//
print("<h2>".$lang_index['text_recent_news'].(user_can('newsmanage') ? " - <font class=\"small\">[<a class=\"altlink\" href=\"news.php\"><b>".$lang_index['text_news_page']."</b></a>]</font>" : "")."</h2>");

$Cache->new_page('recent_news', 86400, true);
if (!$Cache->get_page()){
$latestNews = \App\Models\News::query()->orderByDesc('added')->limit((int)$maxnewsnum_main)->get()->toArray();
if (count($latestNews) > 0)
{
	$Cache->add_whole_row();
	print("<table width=\"100%\"><tr><td class=\"text\"><div style=\"margin-left: 16pt;\">\n");
	$Cache->end_whole_row();
	$news_flag = 0;
	foreach ($latestNews as $newsItem)
	{
		$Cache->add_row();
		$Cache->add_part();
		if ($news_flag < 1) {
			print("<a href=\"javascript: klappe_news('a".$newsItem['id']."')\"><img class=\"minus\" src=\"pic/trans.gif\" id=\"pica".$newsItem['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($newsItem['added'])) . " - " ."<b>". $newsItem['title'] . "</b></a>");
			print("<div id=\"ka".$newsItem['id']."\" style=\"display: block;\"> ".format_comment($newsItem["body"],0)." </div> ");
			$news_flag = $news_flag + 1;
		}
		else
		{
			print("<a href=\"javascript: klappe_news('a".$newsItem['id']."')\"><br /><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica".$newsItem['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($newsItem['added'])) . " - " ."<b>". $newsItem['title'] . "</b></a>");
			print("<div id=\"ka".$newsItem['id']."\" style=\"display: none;\"> ".format_comment($newsItem["body"],0)." </div> ");
		}
		$Cache->end_part();
		$Cache->add_part();
		print("  &nbsp; [<a class=\"faqlink\" href=\"news.php?action=edit&amp;newsid=" . $newsItem['id'] . "\"><b>".$lang_index['text_e']."</b></a>]");
		print(" [<a class=\"faqlink\" href=\"news.php?action=delete&amp;newsid=" . $newsItem['id'] . "\"><b>".$lang_index['text_d']."</b></a>]");
		$Cache->end_part();
		$Cache->end_row();
	}
	$Cache->break_loop();
	$Cache->add_whole_row();
	print("</div></td></tr></table>\n");
	$Cache->end_whole_row();
}
	$Cache->cache_page();
}
echo $Cache->next_row();
while($Cache->next_row()){
	echo $Cache->next_part();
	if (user_can('newsmanage'))
	echo $Cache->next_part();
}
echo $Cache->next_row();
// ------------- end: recent news ------------------//
// ------------- start: hot and classic movies ------------------//
//displayHotAndClassic();
// ------------- end: hot and classic movies ------------------//
// ------------- start: shoutbox ------------------//
if ($showshoutbox_main == "yes") {
    \Nexus\Nexus::js("var SHOUT_CSRF = '" . addslashes(\App\Support\Shoutbox::csrfToken((int)($CURUSER['id'] ?? 0))) . "';", 'footer', false);
?>
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
<?php
	print("<table width=\"100%\"><tr><td class=\"text\">\n");
	print("<iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");
	print("<form action='shoutbox.php' method='get' target='sbox' name='shbox'>\n");
    print(\App\Support\Shoutbox::toolbar('shbox', 'shbox_text'));
    print('<div style="display: flex">');
	print("<label for='shbox_text'>".$lang_index['text_message']."</label><input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />  <input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_index['sumbit_shout']."\" />");
	if ($CURUSER['hidehb'] != 'yes' && $showhelpbox_main =='yes')
		print("<input type='submit' class='btn' name='toguest' value=\"".$lang_index['sumbit_to_guest']."\" />");
	print("<input type='reset' class='btn' value=\"".$lang_index['submit_clear']."\" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />");
	print('</div>');
	print("</form></td></tr></table>");
}
// ------------- end: shoutbox ------------------//

$extraModules = [];
$extraModules = apply_filter('nexus_home_module', $extraModules);
print implode('', $extraModules);

// ------------- start: latest forum posts ------------------//

if ($showlastxforumposts_main == "yes" && $CURUSER)
{
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
	if (count($latestPosts) > 0)
	{
		print("<h2>".$lang_index['text_last_five_posts']."</h2>");
		print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\" align=\"left\">".$lang_index['col_topic_title']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_view']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"left\">".$lang_index['col_posted_at']."</td></tr>");

		foreach ($latestPosts as $postsx)
		{
			print("<tr><td><a href=\"forums.php?action=viewtopic&amp;topicid=".$postsx["tid"]."&amp;page=p".$postsx["pid"]."#pid".$postsx["pid"]."\"><b>".htmlspecialchars($postsx["subject"])."</b></a><br />".$lang_index['text_in']."<a href=\"forums.php?action=viewforum&amp;forumid=".$postsx["forumid"]."\">".htmlspecialchars($postsx["name"])."</a></td><td align=\"center\">".$postsx["views"]."</td><td align=\"center\">" . get_username($postsx["userpost"]) ."</td><td>".gettime($postsx["added"])."</td></tr>");
		}
		print("</table>");
	}
}

// ------------- end: latest forum posts ------------------//
// ------------- start: latest torrents ------------------//

if ($showlastxtorrents_main == "yes") {
		$ltCacheKey = 'index_latest_torrents_grid_v2';
		$ltCacheTtl = 120;
		$ltHtml = $Cache->get_value($ltCacheKey);
		if ($ltHtml === false || $ltHtml === null || $ltHtml === '') {
			$latestTorrents = \App\Repositories\IndexRepository::getLatestTorrents(9);
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
				foreach ($latestTorrents as $torrent)
				{
					$detailsUrl = 'details.php?id=' . (int)$torrent->id . '&hit=1';
					$rawCover = trim((string)($torrent->cover ?? ''));
					$thumbUrl = $rawCover !== '' ? cover_thumb_url($rawCover, 240, 360) : '';
					$typeLabel = trim((string)($torrent->basic_category->name ?? ''));
					if (($torrent->anonymous ?? 'no') === 'yes') {
						$ownerHtml = '<i>Anonymous</i>';
					} else {
						$ownerHtml = get_username((int)$torrent->owner);
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
							<span class="lt-seed" title="<?php echo htmlspecialchars($lang_index['col_seeder']) ?>">&#x25B2; <?php echo (int)$torrent->seeders ?></span>
							<span class="lt-leech" title="<?php echo htmlspecialchars($lang_index['col_leecher']) ?>">&#x25BC; <?php echo (int)$torrent->leechers ?></span>
							<span><?php echo mksize((int)$torrent->size) ?></span>
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

if (get_setting('main.show_top_uploader') == "yes") {
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
        print("<table width='100%'><tr class='tr-top-uploader-tab' title='{$lang_index['top_uploader_toggle_time_range_tab']}'><td class='colhead' align='center' data-table='top-uploader-recently'>{$lang_index['top_uploader_toggle_time_range_recently']}</td><td align='center' data-table='top-uploader-all'>{$lang_index['top_uploader_toggle_time_range_all']}</td></tr></table>");

        $recentUploaders = \App\Repositories\IndexRepository::getTopUploaders(10, 30);
        print ("<table class='top-uploader top-uploader-all' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" style='display: none'><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($allUploaders as $ranking => $uploader)
        {
            print ("<tr><td>" . get_username($uploader->id) . "</td><td align=\"center\">" . $uploader->count . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");

        print ("<table class='top-uploader top-uploader-recently' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($recentUploaders as $ranking => $uploader)
        {
            print ("<tr><td>" . get_username($uploader->id) . "</td><td align=\"center\">" . $uploader->count . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");
    }
}
// ------------- end: top uploader ------------------//

// ------------- start: polls ------------------//
if ($CURUSER && $showpolls_main == "yes")
{
		$pollArr = $Cache->get_value('current_poll_content');
		if ($pollArr === false || $pollArr === null) {
			$pollArr = \App\Repositories\IndexRepository::getCurrentPoll();
			if ($pollArr) {
				$Cache->cache_value('current_poll_content', $pollArr, 7226);
			}
		}
		$pollexists = !empty($pollArr);

		print("<h2>".$lang_index['text_polls']);

		if (user_can('pollmanage'))
		{
			print("<font class=\"small\"> - [<a class=\"altlink\" href=\"makepoll.php?returnto=main\"><b>".$lang_index['text_new']."</b></a>]\n");
			if ($pollexists)
			{
				print(" - [<a class=\"altlink\" href=\"makepoll.php?action=edit&amp;pollid=".$pollArr['id']."&amp;returnto=main\"><b>".$lang_index['text_edit']."</b></a>]\n");
				print(" - [<a class=\"altlink\" href=\"log.php?action=poll&amp;do=delete&amp;pollid=".$pollArr['id']."&amp;returnto=main\"><b>".$lang_index['text_delete']."</b></a>]");
				print(" - [<a class=\"altlink\" href=\"polloverview.php?id=".$pollArr['id']."\"><b>".$lang_index['text_detail']."</b></a>]");
			}
			print("</font>");
		}
		print("</h2>");
		if ($pollexists)
		{
			$pollid = intval($pollArr["id"] ?? 0);
			$question = $pollArr["question"];
			$o = array();
			for ($i = 0; $i <= \App\Models\Poll::MAX_OPTION_INDEX; ++$i) {
				$o[$i] = $pollArr["option{$i}"] ?? '';
			}

			print("<table width=\"100%\"><tr><td class=\"text\" align=\"center\">\n");
			print("<table width=\"59%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"text\" align=\"left\">");
			print("<p align=\"center\"><b>".$question."</b></p>\n");

			$uservote = \App\Repositories\IndexRepository::getUserVote($pollid, $CURUSER["id"]);
			if ($uservote !== null) //user has already voted
			{
				$results = $Cache->get_value('current_poll_result');
				if ($results === false || $results === null) {
					$results = \App\Repositories\IndexRepository::getPollResults($pollid);
					$Cache->cache_value('current_poll_result', $results, 3652);
				}
				$tvotes = array_sum(array_column($results, 'count'));

				print("<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n");
				foreach ($results as $item)
				{
					if ($tvotes == 0)
						$p = 0;
					else
						$p = round($item['count'] / $tvotes * 100);
					$barClass = ($item['index'] == $uservote) ? 'sltbar' : 'unsltbar';
					print("<tr><td width=\"1%\" class=\"embedded nowrap\">" . $item['option'] . "&nbsp;&nbsp;</td><td width=\"99%\" class=\"embedded nowrap\"><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /><img class=\"" . $barClass . "\" src=\"pic/trans.gif\" style=\"width: " . ($p * 3) ."px;\" alt=\"\" /><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /> $p%</td></tr>\n");
				}
				print("</table>\n");
				$tvotes = number_format($tvotes);
				print("<p align=\"center\">".$lang_index['text_votes']." ".$tvotes."</p>\n");

				if (user_can('log'))
					print("<p align=\"center\"><a href=\"log.php?action=poll\">".$lang_index['text_previous_polls']."</a></p>\n");
			}
			else //user has not voted yet
			{
				print("<form method=\"post\" action=\"index.php\">\n");
				for ($i = 0; $i < count($o); ++$i)
				{
					if ($o[$i])
						print("<input type=\"radio\" name=\"choice\" value=\"".$i."\">".$o[$i]."<br />\n");
				}
				print("<br />");
				print("<input type=\"radio\" name=\"choice\" value=\"255\">".$lang_index['radio_blank_vote']."<br />\n");
				print("<p align=\"center\"><input type=\"submit\" class=\"btn\" value=\"".$lang_index['submit_vote']."\" /></p>");
			}
			print("</td></tr></table>");
			print("</td></tr></table>");
		}
}
// ------------- end: polls ------------------//

// ------------- start: stats ------------------//
if ($showstats_main == "yes")
{
?>
<h2><?php echo $lang_index['text_tracker_statistics'] ?></h2>
<table width="100%"><tr><td class="text" align="center">
<table width="60%" class="main" border="1" cellspacing="0" cellpadding="10">
<?php
	$Cache->new_page('stats_users', 3000, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$userStats = \App\Repositories\IndexRepository::getUserStats();
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
	twotd($lang_index['row_users_active_today'],$totalonlinetoday);
	twotd($lang_index['row_users_active_this_week'],$totalonlineweek);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_registered_users'],$registered." / ".number_format($maxusers));
	twotd($lang_index['row_unconfirmed_users'],$unverified);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VIP,false,false,true),$VIP);
	twotd($lang_index['row_donors']." <img class=\"star\" src=\"pic/trans.gif\" alt=\"Donor\" />",$donated);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_warned_users']." <img class=\"warned\" src=\"pic/trans.gif\" alt=\"warned\" />",$warned);
	twotd($lang_index['row_banned_users']." <img class=\"disabled\" src=\"pic/trans.gif\" alt=\"disabled\" />",$disabled);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_male_users'],$registered_male);
	twotd($lang_index['row_female_users'],$registered_female);
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
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$torrentStats = \App\Repositories\IndexRepository::getTorrentStats();
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
	$totaltorrentssize = mksize($torrentStats['totaltorrentssize']);
	$totaluploaded = $torrentStats['totaluploaded'];
	$totaldownloaded = $torrentStats['totaldownloaded'];
	$totaldata = $torrentStats['totaldata'];
?>
<tr>
<?php
	twotd($lang_index['row_torrents'],$torrents);
	twotd($lang_index['row_dead_torrents'],$dead);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_seeders'],$seeders);
	twotd($lang_index['row_leechers'],$leechers);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_peers'],$peers);
	twotd($lang_index['row_seeder_leecher_ratio'],$ratio."%");
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_active_browsing_users'], $activewebusernow);
	twotd($lang_index['row_tracker_active_users'], $activetrackerusernow);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_size_of_torrents'],$totaltorrentssize);
	twotd($lang_index['row_total_uploaded'],mksize($totaluploaded));
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_downloaded'],mksize($totaldownloaded));
	twotd($lang_index['row_total_data'],mksize($totaldata));
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
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$classStats = \App\Repositories\IndexRepository::getClassStats();
	$peasants =  number_format($classStats[UC_PEASANT]);
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
	twotd(get_user_class_name(UC_PEASANT,false,false,true)." <img class=\"leechwarned\" src=\"pic/trans.gif\" alt=\"leechwarned\" />",$peasants);
	twotd(get_user_class_name(UC_USER,false,false,true),$users);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_POWER_USER,false,false,true),$powerusers);
	twotd(get_user_class_name(UC_ELITE_USER,false,false,true),$eliteusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_CRAZY_USER,false,false,true),$crazyusers);
	twotd(get_user_class_name(UC_INSANE_USER,false,false,true),$insaneusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VETERAN_USER,false,false,true),$veteranusers);
	twotd(get_user_class_name(UC_EXTREME_USER,false,false,true),$extremeusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_ULTIMATE_USER,false,false,true),$ultimateusers);
	twotd(get_user_class_name(UC_NEXUS_MASTER,false,false,true),$nexusmasters);
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
if ($showtrackerload == "yes") {
	$uptimeresult=exec('uptime');
	if ($uptimeresult){
?>
<h2><?php echo $lang_index['text_tracker_load'] ?></h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text" align="center">
<?php
	//uptime, work in *nix system
	print ("<div align=\"center\">" . trim($uptimeresult) . "</div>");
	print("</td></tr></table>");
	}
}
// ------------- end: tracker load ------------------//

// ------------- start: disclaimer ------------------//
?>
<h2><?php echo $lang_index['text_disclaimer'] ?></h2>
<table width="100%"><tr><td class="text">
  <?php echo sprintf($lang_index['text_disclaimer_content'], \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName()) ?></td></tr></table>
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
	\App\Models\User::where('id', $CURUSER["id"])->update(['last_home' => now()]);
}
$Cache->delete_value('user_'.$CURUSER["id"].'_unread_news_count');
end_main_frame();
stdfoot();
?>
