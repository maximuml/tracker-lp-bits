<?php
\App\Support\Html::stdhead($lang_viewsnatches['head_snatch_detail']);
\App\Support\Frame::mainFrameOpen();

print("<h1 align=center>".$lang_viewsnatches['text_snatch_detail_for'] . "<a href=details.php?id=" . htmlspecialchars((string) $id) . "><b>".htmlspecialchars($torrentName)."</b></a></h1>");
$seedBoxRep = new \App\Repositories\SeedBoxRepository();
if ($count){
	print("<p align=center>".$lang_viewsnatches['text_users_top_finished_recently']."</p>");
	print("<table border=1 cellspacing=0 cellpadding=5 align=center width=940>\n");
	print("<tr><td class=colhead align=center>".$lang_viewsnatches['col_username']."</td>".(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) ? "<td class=colhead align=center>".$lang_viewsnatches['col_ip']."</td>" : "")."<td class=colhead align=center>".$lang_viewsnatches['col_uploaded']."/".$lang_viewsnatches['col_downloaded']."</td><td class=colhead align=center>".$lang_viewsnatches['col_ratio']."</td><td class=colhead align=center>".$lang_viewsnatches['col_se_time']."</td><td class=colhead align=center>".$lang_viewsnatches['col_le_time']."</td><td class=colhead align=center>".$lang_viewsnatches['col_when_completed']."</td><td class=colhead align=center>".$lang_viewsnatches['col_last_action']."</td><td class=colhead align=center>".$lang_viewsnatches['col_report_user']."</td></tr>");

	foreach ($snatchedRows as $snatchRow) {
	    $arr = (array) $snatchRow;
		//start torrent
		if ($arr["downloaded"] > 0)
		{
			$ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
			$ratio = "<font color=" . \App\Support\Ratio::color($ratio) . ">$ratio</font>";
		}
		elseif ($arr["uploaded"] > 0)
			$ratio = $lang_viewsnatches['text_inf'];
		else
			$ratio = "---";
		$uploaded =\App\Support\Format::size($arr["uploaded"]);
		$downloaded = \App\Support\Format::size($arr["downloaded"]);
		$seedtime = \App\Support\Format::prettyTimeWithLocale($arr["seedtime"]);
		$leechtime = \App\Support\Format::prettyTimeWithLocale($arr["leechtime"]);

		$uprate = $arr["seedtime"] > 0 ? \App\Support\Format::size($arr["uploaded"] / ($arr["seedtime"] + $arr["leechtime"])) : \App\Support\Format::size(0);
		$downrate = $arr["leechtime"] > 0 ? \App\Support\Format::size($arr["downloaded"] / $arr["leechtime"]) : \App\Support\Format::size(0);
		//end

		$highlight = $CURUSER["id"] == $arr["userid"] ? " bgcolor=#00A527" : "";
		$userrow = \App\Support\UserDisplay::row($arr['userid']);
		if ($userrow['privacy'] == 'strong'){
			$username = $lang_viewsnatches['text_anonymous'];
			if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_ANONYMOUS) || $arr["id"] == $CURUSER['id'])
				$username .= "<br />(" . \App\Support\UserDisplay::username($arr['userid']) . ")";
		}
		else $username = \App\Support\UserDisplay::username($arr['userid']);
		$reportImage = "<img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_viewsnatches['title_report']."\" />";
		print("<tr$highlight><td class=rowfollow align=center>" . $username ."</td>".(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) ? "<td class=rowfollow align=center><span class='nowrap'>".$arr['ip'].$seedBoxRep->renderIcon($arr['ip'], $arr['userid'])."</span></td>" : "")."<td class=rowfollow align=center>".$uploaded."@".$uprate.$lang_viewsnatches['text_per_second']."<br />".$downloaded."@".$downrate.$lang_viewsnatches['text_per_second']."</td><td class=rowfollow align=center>$ratio</td><td class=rowfollow align=center>$seedtime</td><td class=rowfollow align=center>$leechtime</td><td class=rowfollow align=center>".\App\Support\Time::format($arr['completedat'],true,false)."</td><td class=rowfollow align=center>".\App\Support\Time::format($arr['last_action'],true,false)."</td><td class=rowfollow align=center style='padding: 0px'>".($userrow['privacy'] != 'strong' || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_ANONYMOUS) ? "<a href=report.php?user={$arr['userid']}>$reportImage</a>" : $reportImage)."</td></tr>\n");
	}
		print("</table>\n");
		print($pagerbottom);
}
else
{
	\App\Support\Html::stdMessage($lang_viewsnatches['std_sorry'], $lang_viewsnatches['std_no_snatched_users']);
}
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
