<?php
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
$CURUSER = \App\Support\SupportContext::getUser() ?? [];

if (!function_exists('usershare_table')) { function usershare_table($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();
?>
<tr>
<td class="colhead"><?php echo $lang_topten['col_rank'] ?></td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_user'] ?> </td>
<td class="colhead"> <?php echo $lang_topten['col_uploaded'] ?> </td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_ul_speed'] ?> </td>
<td class="colhead"> <?php echo $lang_topten['col_downloaded'] ?></td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_dl_speed'] ?> </td>
<td class="colhead" align="right"> <?php echo $lang_topten['col_ratio'] ?> </td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_joined'] ?> </td>
</tr>
<?php
$num = 0;
foreach ($res as $a) { $a = (array) $a;
	++$num;
	$uploaded = (float) ($a["uploaded"] ?? 0);
	$downloaded = (float) ($a["downloaded"] ?? 0);
	$upspeed = (float) ($a["upspeed"] ?? 0);
	$downspeed = (float) ($a["downspeed"] ?? 0);
	if ($downloaded)
	{
		$ratio = $uploaded / $downloaded;
		$color = \App\Support\Ratio::color($ratio);
		$ratio = number_format($ratio, 2);
		if ($color)
		$ratio = "<font color=\"$color\">$ratio</font>";
	}
	else
		$ratio = $lang_topten['text_inf'];
	print("<tr><td class=\"rowfollow\" align=\"center\">$num</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($a["userid"]) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($uploaded) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($upspeed) . "/s" .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($downloaded) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($downspeed) . "/s" .
	"</td><td class=\"rowfollow\" align=\"right\">" . $ratio .
	"</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\Time::format($a["added"],true,false). "</td></tr>");
}
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
} }

if (!function_exists('_torrenttable')) { function _torrenttable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();
?>
<tr>
<td class="colhead" align="center"><?php echo $lang_topten['col_rank'] ?></td>
<td class="colhead" align="left"><?php echo $lang_topten['col_name'] ?></td>
<td class="colhead" align="right"><?php echo "<img class=\"snatched\" src=\"pic/trans.gif\" alt=\"snatched\" title=\"".$lang_topten['title_sna']."\" />" ?></td>
<td class="colhead" align="right"><?php echo $lang_topten['col_data'] ?></td>
<td class="colhead" align="right"><?php echo "<img class=\"seeders\" src=\"pic/trans.gif\" alt=\"seeders\" title=\"".$lang_topten['title_se']."\" />" ?></td>
<td class="colhead" align="right"><?php echo "<img class=\"leechers\" src=\"pic/trans.gif\" alt=\"leechers\" title=\"".$lang_topten['col_le']."\" />" ?></td>
<td class="colhead" align="right"><?php echo $lang_topten['col_to'] ?></td>
<td class="colhead" align="right"><?php echo $lang_topten['col_ratio'] ?></td>
</tr>
<?php
$num = 0;
foreach ($res as $a) { $a = (array) $a;
	++$num;
	$seeders = (int) ($a["seeders"] ?? 0);
	$leechers = (int) ($a["leechers"] ?? 0);
	$data = (float) ($a["data"] ?? 0);
	$timesCompleted = (int) ($a["times_completed"] ?? 0);
	if ($leechers)
	{
		$r = $seeders / $leechers;
		$ratio = "<font color=\"" . \App\Support\Ratio::color($r) . "\">" . number_format($r, 2) . "</font>";
	}
	else
	$ratio = $lang_topten['text_inf'];
	print("<tr><td class=\"rowfollow\" align=\"center\">$num</td><td class=\"rowfollow\" align=\"left\"><a href=\"details.php?id=" . $a["id"] . "&amp;hit=1\"><b>" .
	$a["name"] . "</b></a></td><td class=\"rowfollow\" align=\"right\">" . number_format($timesCompleted) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($data) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($seeders) .
	"</td><td class=\"rowfollow\" align=\"right\">" . number_format($leechers) . "</td><td class=\"rowfollow\" align=\"right\">" . ($leechers + $seeders) .
	"</td><td class=\"rowfollow\" align=\"right\">$ratio</td>\n");
}
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
} }

if (!function_exists('countriestable')) { function countriestable($res, $frame_caption, $what)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();
?>
<tr>
<td class="colhead"><?php echo $lang_topten['col_rank'] ?></td>
<td class="colhead" align="left"><?php echo $lang_topten['col_country'] ?></td>
<td class="colhead" align="right"><?php echo $what?></td>
</tr>
<?php
$num = 0;
foreach ($res as $a) { $a = (array) $a;
	++$num;
	if ($what == $lang_topten['col_users'])
	$value = number_format((float) ($a["num"] ?? 0));
	elseif ($what == $lang_topten['col_uploaded'])
	$value = \App\Support\Format::size((float) ($a["ul"] ?? 0));
	elseif ($what == $lang_topten['col_average'])
	$value = \App\Support\Format::size((float) ($a["ul_avg"] ?? 0));
	elseif ($what == $lang_topten['col_ratio'])
	$value = number_format((float) ($a["r"] ?? 0), 2);
	print("<tr><td class=\"rowfollow\" align=\"center\">$num</td><td class=\"rowfollow\" align=\"left\"><table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">".
	"<img align=\"center\" src=\"pic/flag/{$a['flagpic']}\" alt=\"\" /></td><td class=\"embedded\" style='padding-left: 5px'><b>{$a['name']}</b></td>".
	"</tr></table></td><td class=\"rowfollow\" align=\"right\">$value</td></tr>\n");
}
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
} }

if (!function_exists('peerstable')) { function peerstable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$lang_topten['col_upload_rate']."</td><td class=\"colhead\">".$lang_topten['col_download_rate']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$uprate = (float) ($arr["uprate"] ?? 0);
		$downrate = (float) ($arr["downrate"] ?? 0);
		print("<tr><td class=\"rowfollow\">$n</td><td class=\"rowfollow\">" . \App\Support\UserDisplay::username($arr["userid"]) . "</td><td class=\"rowfollow\">" . \App\Support\Format::size($uprate) . "/s</td><td class=\"rowfollow\">" . \App\Support\Format::size($downrate) . "/s</td></tr>\n");
		++$n;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('bonustable')) { function bonustable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$lang_topten['col_bonus']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$seedbonus = (float) ($arr["seedbonus"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["id"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($seedbonus, 1) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('charityTable')) { function charityTable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$lang_topten['col_bonus']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$charity = (float) ($arr["charity"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["id"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($charity) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('cmttable')) { function cmttable($res, $frame_caption, $col2_name)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$col2_name."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$num = (int) ($arr["num"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["userid"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($num) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('locationtable')) { function locationtable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_location']."</td><td class=\"colhead\">".$lang_topten['col_number']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$num = (int) ($arr["num"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["location_name"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($num) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('postable')) { function postable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$lang_topten['col_topics']."</td><td class=\"colhead\">".$lang_topten['col_posts']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$usertopics = (int) ($arr["usertopics"] ?? 0);
		$userposts = (int) ($arr["userposts"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["userid"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($usertopics) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($userposts) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('bigtopic_table')) { function bigtopic_table($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_subject']."</td><td class=\"colhead\">".$lang_topten['col_posts']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$postnum = (int) ($arr["postnum"] ?? 0);
		$topic = "<a href =\"forums.php?action=viewtopic&forumid=" . $arr["forumid"] . "&topicid=" . $arr["topicid"] . "\">" . $arr["topicsubject"] . "</a>";
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . $topic. "</td><td class=\"rowfollow\" align=\"right\">" . number_format($postnum) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('donortable')) { function donortable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_username']."</td><td class=\"colhead\">".$lang_topten['col_donated_usd']."</td><td class=\"colhead\">".$lang_topten['col_donated_cny']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$donated = (float) ($arr["donated"] ?? 0);
		$donatedCny = (float) ($arr["donated_cny"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($arr["id"]) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($donated, 2) . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($donatedCny, 2) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('clienttable')) { function clienttable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_name']."</td><td class=\"colhead\">".$lang_topten['col_number']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$clientNum = (int) ($arr["client_num"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . $arr["client_name"] . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($clientNum) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('lastsearch_table')) { function lastsearch_table($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_keyword']."</td><td class=\"colhead\">".$lang_topten['col_datetime']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\"><a href=\"torrents.php?search=" . rawurlencode($arr["keywords"]) . "\">" . $arr["keywords"] . "</a></td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Time::format($arr["adddate"], true,false) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('search_ranktable')) { function search_ranktable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_keyword']."</td><td class=\"colhead\">".$lang_topten['col_times']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$count = (int) ($arr["count"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\"><a href=\"torrents.php?search=" . rawurlencode($arr["keywords"]) . "\">" . $arr["keywords"] . "</a></td><td class=\"rowfollow\" align=\"right\">" . number_format($count) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('supply_snatchtable')) { function supply_snatchtable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();
?>
<tr>
<td class="colhead"><?php echo $lang_topten['col_rank'] ?></td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_user'] ?> </td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_torrent_uploaded'] ?> </td>
<td class="colhead"> <?php echo $lang_topten['col_uploaded'] ?> </td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_torrent_downloaded'] ?> </td>
<td class="colhead"> <?php echo $lang_topten['col_downloaded'] ?></td>
<td class="colhead" align="right"> <?php echo $lang_topten['col_ratio'] ?> </td>
<td class="colhead" align="left"> <?php echo $lang_topten['col_joined'] ?> </td>
</tr>
<?php
$num = 0;
foreach ($res as $a) { $a = (array) $a;
	++$num;
	$supplied = (int) ($a["supplied"] ?? 0);
	$uploaded = (float) ($a["uploaded"] ?? 0);
	$snatched = (int) ($a["snatched"] ?? 0);
	$downloaded = (float) ($a["downloaded"] ?? 0);
	if ($downloaded)
	{
		$ratio = $uploaded / $downloaded;
		$color = \App\Support\Ratio::color($ratio);
		$ratio = number_format($ratio, 2);
		if ($color)
		$ratio = "<font color=\"$color\">$ratio</font>";
	}
	else
	$ratio = $lang_topten['text_inf'];
	print("<tr><td class=\"rowfollow\" align=\"center\">$num</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\UserDisplay::username($a["userid"]) .
	"</td><td class=\"rowfollow\" align=\"right\">" . number_format($supplied) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($uploaded) .
	"</td><td class=\"rowfollow\" align=\"right\">" . number_format($snatched) .
	"</td><td class=\"rowfollow\" align=\"right\">" . \App\Support\Format::size($downloaded) .
	"</td><td class=\"rowfollow\" align=\"right\">" . $ratio .
	"</td><td class=\"rowfollow\" align=\"left\">" . \App\Support\Time::format($a["added"]). "</td></tr>");
}
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
} }

if (!function_exists('stylesheettable')) { function stylesheettable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_name']."</td><td class=\"colhead\">".$lang_topten['col_number']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$stylesheetNum = (int) ($arr["stylesheet_num"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . $arr["stylesheet_name"] . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($stylesheetNum) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

if (!function_exists('languagetable')) { function languagetable($res, $frame_caption)
{
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
	\App\Support\Html::beginFrame($frame_caption, true);
	\App\Support\Html::beginTable();

	print("<tr><td class=\"colhead\">".$lang_topten['col_rank']."</td><td class=\"colhead\">".$lang_topten['col_name']."</td><td class=\"colhead\">".$lang_topten['col_number']."</td></tr>");

	$n = 1;
	foreach ($res as $arr) { $arr = (array) $arr;
		$langNum = (int) ($arr["lang_num"] ?? 0);
		print("<tr><td class=\"rowfollow\" align=\"center\">$n</td><td class=\"rowfollow\" align=\"left\">" . $arr["lang_name"] . "</td><td class=\"rowfollow\" align=\"right\">" . number_format($langNum) . "</td></tr>\n");
		$n++;
	}

	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
} }

function topten_link_line(int $type, string $subtype, array $limits, array $lang): string
{
    if (empty($limits)) {
        return '';
    }

    $links = [];
    foreach ($limits as $lim) {
        $label = match ($lim) {
            100 => $lang['text_one_hundred'] ?? 'Top 100',
            250 => $lang['text_top_250'] ?? 'Top 250',
            25 => 'Top 25',
            50 => 'Top 50',
            default => "Top {$lim}",
        };
        $links[] = '<a class="altlink" href="topten.php?type=' . $type . '&amp;lim=' . $lim . '&amp;subtype=' . htmlspecialchars($subtype, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
    }

    return ' <font class="small"> - [' . implode('] - [', $links) . ']</font>';
}

\App\Support\Html::stdhead($lang_topten['head_top_ten'] ?? 'Top 10');
\App\Support\Frame::mainFrameOpen();

$isDefault = ($limit === 10 && $subtype === null);

print('<p align="center">' .
    ($type === 1 && $isDefault ? '<b>' . ($lang['text_users'] ?? 'Users') . '</b>' : '<a href="topten.php?type=1">' . ($lang['text_users'] ?? 'Users') . '</a>') . ' | ' .
    ($type === 2 && $isDefault ? '<b>' . ($lang['text_torrents'] ?? 'Torrents') . '</b>' : '<a href="topten.php?type=2">' . ($lang['text_torrents'] ?? 'Torrents') . '</a>') . ' | ' .
    ($type === 3 && $isDefault ? '<b>' . ($lang['text_countries'] ?? 'Countries') . '</b>' : '<a href="topten.php?type=3">' . ($lang['text_countries'] ?? 'Countries') . '</a>') . ' | ' .
    ($type === 5 && $isDefault ? '<b>' . ($lang['text_community'] ?? 'Community') . '</b>' : '<a href="topten.php?type=5">' . ($lang['text_community'] ?? 'Community') . '</a>') . ' | ' .
    ($type === 6 && $isDefault ? '<b>' . ($lang['text_other'] ?? 'Other') . '</b>' : '<a href="topten.php?type=6">' . ($lang['text_other'] ?? 'Other') . '</a>') .
    "</p>\n");

foreach ($sections as $section) {
    $renderer = (string) $section['renderer'];
    $caption = (string) $section['caption'];
    $caption .= topten_link_line($type, (string) $section['subtype'], $section['limits'] ?? [], $lang);

    $args = [$section['data'], $caption];
    if (array_key_exists('what', $section) && $section['what'] !== null) {
        $args[] = (string) $section['what'];
    }

    $renderer(...$args);
}

\App\Support\Frame::mainFrameClose();
print("<p><font class=\"small\">" . ($lang['text_this_page_last_updated'] ?? 'This page last updated ') . date('Y-m-d H:i:s') . ', ' . ($lang['text_started_recording_date'] ?? 'Started recording account xfer stats on ') . ($dateFounded ?? '') . ($lang['text_update_interval'] ?? '') . "</font></p>\n");
\App\Support\Html::stdfoot();
