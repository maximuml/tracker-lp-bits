<?php
$lang_cheaterbox = (array) (\App\Support\SupportContext::getGlobal('lang_cheaterbox') ?? []);
$lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);

\App\Support\Html::stdhead($lang_cheaterbox['head_cheaterbox'] ?? 'Cheaterbox');
?>
<style type="text/css">
table.cheaterbox td
{
	text-align: center;
}
</style>
<?php
\App\Support\Frame::mainFrameOpen();
print("<h1 align=center>".$lang_cheaterbox['text_cheaterbox']."</h1>");
print("<table class=cheaterbox border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr><td class=colhead><nobr>".$lang_cheaterbox['col_added']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_suspect']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_hit']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_torrent']."</td><td class=colhead>".$lang_cheaterbox['col_ul']."</td><td class=colhead>".$lang_cheaterbox['col_dl']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_ann_time']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_seeders']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_leechers']."</nobr></td><td class=colhead>".$lang_cheaterbox['col_comment']."</td><td class=colhead><nobr>".$lang_cheaterbox['col_dealt_with']."</nobr></td><td class=colhead><nobr>".$lang_cheaterbox['col_action']."</nobr></td></tr>");

print("<form method=post action=cheaterbox.php>");
foreach ($rows as $row) {
	printf(
		'<tr><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%d sec</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow>%s</td><td class=rowfollow><input type="checkbox" name="delcheater[]" value="%d" /></td></tr>' . "\n",
		$row['added_formatted'],
		\App\Support\UserDisplay::username($row['userid']),
		$row['hit'],
		$row['torrent'],
		$row['uploaded_str'],
		$row['downloaded_str'],
		$row['anctime'],
		$row['seeders'],
		$row['leechers'],
		htmlspecialchars($row['comment']),
		$row['dealtwith_html'],
		$row['id']
	);
}
?>
<tr><td class="colhead" colspan="12" style="text-align: right"><input class=btn type="button" value="<?php echo $lang_functions['input_check_all']; ?>" onClick="this.value=check(form,'<?php echo $lang_functions['input_check_all'] ?>','<?php echo $lang_functions['input_uncheck_all'] ?>')"><input type="submit" name="setdealt" value="<?php echo $lang_cheaterbox['submit_set_dealt']?>" /><input type="submit" name="delete" value="<?php echo $lang_cheaterbox['submit_delete']?>" /></td></tr>
</form>
<?php
print("</table>");
print($pagerbottom);
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
