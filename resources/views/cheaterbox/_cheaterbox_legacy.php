<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
user_can('staffmem', true);


if (!empty(\App\Support\SupportContext::getPost('setdealt'))) {
    if (empty(\App\Support\SupportContext::getPost('delcheater'))) {
        stderr("Error", $lang_functions['select_at_least_one_record']);
    }
//	$res = sql_query ("SELECT id FROM cheaters WHERE dealtwith=0 AND id IN (" . implode(", ", \App\Support\SupportContext::getPost('delcheater')) . ")");
//	while ($arr = mysql_fetch_assoc($res))
//		sql_query ("UPDATE cheaters SET dealtwith=1, dealtby = {$CURUSER['id']} WHERE id = {$arr['id']}") or sqlerr();

	\App\Models\Cheater::query()->whereIn('id', \App\Support\SupportContext::getPost('delcheater'))
        ->where('dealtwith', 0)
        ->update(['dealtwith' => 1, 'dealtby' => $CURUSER['id']])
    ;
	$Cache->delete_value('staff_new_cheater_count');
}
elseif (!empty(\App\Support\SupportContext::getPost('delete'))) {
    if (empty(\App\Support\SupportContext::getPost('delcheater'))) {
        stderr("Error", $lang_functions['select_at_least_one_record']);
    }
//	$res = sql_query ("SELECT id FROM cheaters WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('delcheater')) . ")");
//	while ($arr = mysql_fetch_assoc($res))
//		sql_query ("DELETE from cheaters WHERE id = {$arr['id']}") or sqlerr();

	\App\Models\Cheater::query()->whereIn('id', \App\Support\SupportContext::getPost('delcheater'))->delete();
	$Cache->delete_value('staff_new_cheater_count');
}

$count = \Nexus\Database\NexusDB::table('cheaters')->count();
if (!$count){
	stderr($lang_cheaterbox['std_oho'], $lang_cheaterbox['std_no_suspect_detected']);
}
$perpage = 50;
list($pagertop, $pagerbottom, , $offset, $rpp) = pager($perpage, $count, "cheaterbox.php?");
stdhead($lang_cheaterbox['head_cheaterbox']);
?>
<style type="text/css">
table.cheaterbox td
{
	text-align: center;
}
</style>
<?php
begin_main_frame();
print("<h1 align=center>".$lang_cheaterbox['text_cheaterbox']."</h1>");
print("<table class=cheaterbox border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr><td class=colhead><nobr>".$lang_cheaterbox['col_added']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_suspect']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_hit']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_torrent']."</td><td class=colhead>".$lang_cheaterbox['col_ul']."</td><td class=colhead>".$lang_cheaterbox['col_dl']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_ann_time']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_seeders']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_leechers']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_comment']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_dealt_with']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_action']."</nobr></td></tr>");

print("<form method=post action=cheaterbox.php>");
$cheaters = \Nexus\Database\NexusDB::table('cheaters')
    ->orderBy('dealtwith')
    ->orderByDesc('id')
    ->offset($offset)
    ->limit($rpp)
    ->get();

foreach ($cheaters as $cheaterRow) {
	$row = (array) $cheaterRow;
	$upspeed = ($row['uploaded'] > 0 ? $row['uploaded'] / $row['anctime'] : 0);
	$lespeed = ($row['downloaded'] > 0 ? $row['downloaded'] / $row['anctime'] : 0);
	$torrentName = \App\Models\Torrent::query()->where('id', $row['torrentid'])->value('name');
	if ($torrentName)
		$torrent = "<a href=details.php?id=".$row['torrentid'].">".htmlspecialchars($torrentName)."</a>";
	else $torrent = $lang_cheaterbox['text_torrent_does_not_exist'];
	if ($row['dealtwith'])
		$dealtwith = "<font color=green>".$lang_cheaterbox['text_yes']."</font> - " . get_username($row['dealtby']);
	else
		$dealtwith = "<font color=red>".$lang_cheaterbox['text_no']."</font>";

	print("<tr><td class=rowfollow>".gettime($row['added'])."</td><td class=rowfollow>" . get_username($row['userid']) . "</td><td class=rowfollow>" . $row['hit'] . "</td><td class=rowfollow>" . $torrent . "</td><td class=rowfollow>".mksize($row['uploaded']).($upspeed ? " @ ".mksize($upspeed)."/s" : "")."</td><td class=rowfollow>".mksize($row['downloaded']).($lespeed ? " @ ".mksize($lespeed)."/s" : "")."</td><td class=rowfollow>".$row['anctime']." sec"."</td><td class=rowfollow>".$row['seeders']."</td><td class=rowfollow>".$row['leechers']."</td><td class=rowfollow>".htmlspecialchars($row['comment'])."</td><td class=rowfollow>".$dealtwith."</td><td class=rowfollow><input type=\"checkbox\" name=\"delcheater[]\" value=\"" . $row['id'] . "\" /></td></tr>\n");
}
?>
<tr><td class="colhead" colspan="12" style="text-align: right"><input class=btn type="button" value="<?php echo $lang_functions['input_check_all']; ?>" onClick="this.value=check(form,'<?php echo $lang_functions['input_check_all'] ?>','<?php echo $lang_functions['input_uncheck_all'] ?>')"><input type="submit" name="setdealt" value="<?php echo $lang_cheaterbox['submit_set_dealt']?>" /><input type="submit" name="delete" value="<?php echo $lang_cheaterbox['submit_delete']?>" /></td></tr>
</form>
<?php
print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();
?>
