<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER);

$count = \Nexus\Database\NexusDB::table('reports')->count();
if (!$count){
	stderr($lang_reports['std_oho'], $lang_reports['std_no_report']);
}
stdhead($lang_reports['head_reports']);
$perpage = 10;
list($pagertop, $pagerbottom, , $offset, $rpp) = pager($perpage, $count, "reports.php?");
begin_main_frame();
print("<h1 align=center>".$lang_reports['text_reports']."</h1>");
print("<table border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr><td class=colhead><nobr>".$lang_reports['col_added']."</nobr></td><td class=colhead>".$lang_reports['col_reporter']."</td><td class=colhead>".$lang_reports['col_reporting']."</td><td class=colhead><nobr>".$lang_reports['col_type']."</nobr></td><td class=colhead>".$lang_reports['col_reason']."</td><td class=colhead><nobr>".$lang_reports['col_dealt_with']."</nobr></td><td class=colhead><nobr>".$lang_reports['col_action']."</nobr></td>");

print("<form method=post action=takeupdate.php>");
$reportRows = \Nexus\Database\NexusDB::table('reports')
    ->orderBy('dealtwith')
    ->orderByDesc('id')
    ->offset($offset)
    ->limit($rpp)
    ->get();

foreach ($reportRows as $reportRow) {
	$row = (array) $reportRow;
	if ($row['dealtwith'])
		$dealtwith = "<font color=green>".$lang_reports['text_yes']."</font> - " . get_username($row['dealtby']);
	else
		$dealtwith = "<font color=red>".$lang_reports['text_no']."</font>";
	switch ($row['type'])
	{
		case "torrent":
		{
			$type = $lang_reports['text_torrent'];
			$torrent = \App\Models\Torrent::query()->where('id', $row['reportid'])->first(['id', 'name']);
			if (!$torrent)
				$reporting = $lang_reports['text_torrent_does_not_exist'];
			else
			{
				$arr = $torrent->toArray();
				$reporting = "<a href=details.php?id=".$arr['id'].">".htmlspecialchars($arr['name'])."</a>";
			}
			break;
		}
		case "user":
		{
			$type = $lang_reports['text_user'];
			$userId = \App\Models\User::query()->where('id', $row['reportid'])->value('id');
			if (!$userId)
				$reporting = $lang_reports['text_user_does_not_exist'];
			else
			{
				$reporting = get_username($userId);
			}
			break;
		}
		case "offer":
		{
			$type = $lang_reports['text_offer'];
			$offer = \App\Models\Offer::query()->where('id', $row['reportid'])->first(['id', 'name']);
			if (!$offer)
				$reporting = $lang_reports['text_offer_does_not_exist'];
			else
			{
				$arr = $offer->toArray();
				$reporting = "<a href=\"offers.php?id=".$arr['id']."&off_details=1\">".htmlspecialchars($arr['name'])."</a>";
			}
			break;
		}
		case "post":
		{
			$type = $lang_reports['text_forum_post'];
			$arr = (array) \Nexus\Database\NexusDB::table('topics')
			    ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
			    ->where('posts.id', $row['reportid'])
			    ->first(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid']);
			if (empty($arr))
				$reporting = $lang_reports['text_post_does_not_exist'];
			else
			{
				$reporting = $lang_reports['text_post_id'].$row['reportid'].$lang_reports['text_of_topic']."<b><a href=\"forums.php?action=viewtopic&topicid=".$arr['topicid']."&page=p".htmlspecialchars($row['reportid'])."#pid".htmlspecialchars($row['reportid'])."\">".htmlspecialchars($arr['subject'])."</a></b>".$lang_reports['text_by'].get_username($arr['postuserid']);
			}
			break;
		}
		case "comment":
		{
			$type = $lang_reports['text_comment'];
			$comment = \App\Models\Comment::query()->where('id', $row['reportid'])->first(['id', 'user', 'torrent', 'offer']);
			if (!$comment)
				$reporting = $lang_reports['text_comment_does_not_exist'];
			else
			{
				$arr = $comment->toArray();
				if ($arr['torrent'])
				{
					$name = \App\Models\Torrent::query()->where('id', $arr['torrent'])->value('name');
					$url = "details.php?id=".$arr['torrent']."#cid".$row['reportid'];
					$of = $lang_reports['text_of_torrent'];
				}
				elseif ($arr['offer'])
				{
					$name = \App\Models\Offer::query()->where('id', $arr['offer'])->value('name');
					$url = "offers.php?id=".$arr['offer']."&off_details=1#cid".$row['reportid'];
					$of = $lang_reports['text_of_offer'];
				} else //Comment belongs to no one
					$of = "unknown";
				$reporting = $lang_reports['text_comment_id'].$row['reportid'].$of."<b><a href=\"".$url."\">".htmlspecialchars($name)."</a></b>".$lang_reports['text_by'].get_username($arr['user']);
			}
			break;
		}
		default:
		{
			break;
		}
	}

	print("<tr><td class=rowfollow><nobr>".\App\Support\Time::format($row['added'])."</nobr></td><td class=rowfollow>" . get_username($row['addedby']) . "</td><td class=rowfollow>".$reporting."</td><td class=rowfollow><nobr>".$type."</nobr></td><td class=rowfollow>".htmlspecialchars($row['reason'])."</td><td class=rowfollow><nobr>".$dealtwith."</nobr></td><td class=rowfollow><input type=\"checkbox\" name=\"delreport[]\" value=\"" . $row['id'] . "\" /></td></tr>\n");
}
?>
<tr><td class="colhead" colspan="7" align="right"><input type="submit" name="setdealt" value="<?php echo $lang_reports['submit_set_dealt']?>" /><input type="submit" name="delete" value="<?php echo $lang_reports['submit_delete']?>" /></td></tr>
</form>
<?php
print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();
