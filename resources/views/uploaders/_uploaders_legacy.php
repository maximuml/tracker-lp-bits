<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (get_user_class() < UC_UPLOADER)
    permissiondenied();

$year=intval(\App\Support\SupportContext::getQuery('year') ?? 0);
if (!$year || $year < 2000)
$year=date('Y');
$month=intval(\App\Support\SupportContext::getQuery('month') ?? 0);
if (!$month || $month<=0 || $month>12)
$month=date('m');
$order=\App\Support\SupportContext::getQuery('order') ?? '';
if (!in_array($order, array('username', 'torrent_size', 'torrent_count')))
	$order='username';
$sortColumn = match ($order) {
    'torrent_size' => \Nexus\Database\NexusDB::raw('SUM(torrents.size)'),
    'torrent_count' => \Nexus\Database\NexusDB::raw('COUNT(torrents.id)'),
    default => 'users.username',
};
$sortDirection = $order === 'username' ? 'ASC' : 'DESC';
if (empty($nexus_legacy_layout)) { stdhead($lang_uploaders['head_uploaders']); }
if (empty($nexus_legacy_layout)) { begin_main_frame(); }
?>
<div style="width: 940px">
<?php
$year2 = substr($datefounded, 0, 4);
$yearfounded = ($year2 ? $year2 : 2007);
$yearnow=date("Y");

$timestart=strtotime($year."-".$month."-01 00:00:00");
$sqlstarttime=date("Y-m-d H:i:s", $timestart);
$timeend=strtotime("+1 month", $timestart);
$sqlendtime=date("Y-m-d H:i:s", $timeend);

print("<h1 align=\"center\">".$lang_uploaders['text_uploaders']." - ".date("Y-m",$timestart)."</h1>");

$yearselection="<select name=\"year\">";
for($i=$yearfounded; $i<=$yearnow; $i++)
	$yearselection .= "<option value=\"".$i."\"".($i==$year ? " selected=\"selected\"" : "").">".$i."</option>";
$yearselection.="</select>";

$monthselection="<select name=\"month\">";
for($i=1; $i<=12; $i++)
	$monthselection .= "<option value=\"".$i."\"".($i==$month ? " selected=\"selected\"" : "").">".$i."</option>";
$monthselection.="</select>";

?>
<div>
<form method="get" action="?">
<span>
<?php echo $lang_uploaders['text_select_month']?><?php echo $yearselection?>&nbsp;&nbsp;<?php echo $monthselection?>&nbsp;&nbsp;<input type="submit" value="<?php echo $lang_uploaders['submit_go']?>" />
</span>
</form>
</div>

<?php
$num = \App\Models\User::query()->where('class', '>=', UC_UPLOADER)->count();
if (!$num)
	print("<p align=\"center\">".$lang_uploaders['text_no_uploaders_yet']."</p>");
else{
?>
<div style="margin-top: 8px">
<?php
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\" width=\"97%\"><tr>");
	print("<td class=\"colhead\">".$lang_uploaders['col_username']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_torrents_size']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_torrents_num']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_last_upload_time']."</td>");
	print("<td class=\"colhead\">".$lang_uploaders['col_last_upload']."</td>");
	print("</tr>");
	$uploaders = \Nexus\Database\NexusDB::table('torrents')
	    ->leftJoin('users', 'torrents.owner', '=', 'users.id')
	    ->where('users.class', '>=', UC_UPLOADER)
	    ->where('torrents.added', '>', $sqlstarttime)
	    ->where('torrents.added', '<', $sqlendtime)
	    ->groupBy('users.id', 'users.username')
	    ->orderBy($sortColumn, $sortDirection)
	    ->get(['users.id AS userid', 'users.username AS username', \Nexus\Database\NexusDB::raw('COUNT(torrents.id) AS torrent_count'), \Nexus\Database\NexusDB::raw('SUM(torrents.size) AS torrent_size')]);
	$hasupuserid=array();
	foreach ($uploaders as $uploader) {
		$row = (array) $uploader;
		$lastTorrent = \Nexus\Database\NexusDB::table('torrents')->where('owner', $row['userid'])->orderByDesc('id')->first(['id', 'name', 'added']);
		$row2 = $lastTorrent ? (array) $lastTorrent : [];
		print("<tr>");
		print("<td class=\"colfollow\">".get_username($row['userid'], false, true, true, false, false, true)."</td>");
		print("<td class=\"colfollow\">".($row['torrent_size'] ? mksize($row['torrent_size']) : "0")."</td>");
		print("<td class=\"colfollow\">".$row['torrent_count']."</td>");
		print("<td class=\"colfollow\">".($row2['added'] ? gettime($row2['added']) : $lang_uploaders['text_not_available'])."</td>");
		print("<td class=\"colfollow\">".($row2['name'] ? "<a href=\"details.php?id=".$row2['id']."\">".htmlspecialchars($row2['name'])."</a>" : $lang_uploaders['text_not_available'])."</td>");
		print("</tr>");
		$hasupuserid[]=$row['userid'];
	}
	$nonUploaders = \App\Models\User::query()
	    ->where('class', '>=', UC_UPLOADER)
	    ->when(!empty($hasupuserid), function ($q) use ($hasupuserid) {
	        $q->whereNotIn('id', $hasupuserid);
	    })
	    ->orderBy('username')
	    ->get(['id AS userid', 'username']);
    $count = 0;
	foreach ($nonUploaders as $nonUploader) {
		$row = $nonUploader->toArray();
		$lastTorrent = \Nexus\Database\NexusDB::table('torrents')->where('owner', $row['userid'])->orderByDesc('id')->first(['id', 'name', 'added']);
		$row2 = $lastTorrent ? (array) $lastTorrent : [];
		print("<tr>");
		print("<td class=\"colfollow\">".get_username($row['userid'], false, true, true, false, false, true)."</td>");
		print("<td class=\"colfollow\">".($row['torrent_size'] ? mksize($row['torrent_size']) : "0")."</td>");
		print("<td class=\"colfollow\">".$row['torrent_count']."</td>");
		print("<td class=\"colfollow\">".($row2['added'] ? gettime($row2['added']) : $lang_uploaders['text_not_available'])."</td>");
		print("<td class=\"colfollow\">".($row2['name'] ? "<a href=\"details.php?id=".$row2['id']."\">".htmlspecialchars($row2['name'])."</a>" : $lang_uploaders['text_not_available'])."</td>");
		print("</tr>");
		$count++;
		unset($row2);
	}
	print("</table>");
?>
</div>
<div style="margin-top: 8px; margin-bottom: 8px;">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_uploaders['text_order_by']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=username"><?php echo $lang_uploaders['text_username']?></a></li>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=torrent_size"><?php echo $lang_uploaders['text_torrent_size']?></a></li>
<li><a href="?year=<?php echo $year?>&amp;month=<?php echo $month?>&amp;order=torrent_count"><?php echo $lang_uploaders['text_torrent_num']?></a></li>
</ul>
</span>
</span>
</div>
<?php
}
?>
</div>
<?php
if (empty($nexus_legacy_layout)) { end_main_frame(); }
if (empty($nexus_legacy_layout)) { stdfoot(); }
