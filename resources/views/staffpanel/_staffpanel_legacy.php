<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$langFile = ROOT_PATH . get_langfile_path();
if (file_exists($langFile)) {
	require $langFile;
}
\App\Support\Html::stdhead($lang_staffpanel["Administration"] ?? 'Administration');
print("<h1 align=center>" . ($lang_staffpanel["Administration"] ?? 'Administration') . "</h1>");
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
{
	\App\Support\Html::stdMessage("Error", "Access denied!!!");
	\App\Support\Html::stdfoot();
	return;
}
\App\Support\Frame::mainFrameOpen();

///////////////////// SysOp Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (\App\Support\UserDisplay::currentClass() >= UC_SYSOP) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For SysOp Only"] ?? 'For SysOp Only') . "  ::..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$sysopPanels = \Nexus\Database\NexusDB::table('sysoppanel')->get();
	foreach ($sysopPanels as $panelRow) {
		$row = (array) $panelRow;
		$id = $row['id'];
		$name = $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}
	print("</table>");
	print("<br />");
	print("<br />");
}
///////////////////// Admin Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (\App\Support\UserDisplay::currentClass() >= UC_ADMINISTRATOR) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For Administrator Only"] ?? 'For Administrator Only') . " :..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$adminPanels = \Nexus\Database\NexusDB::table('adminpanel')->get();
	foreach ($adminPanels as $panelRow) {
		$row = (array) $panelRow;
		$id = $row['id'];
		$name =  $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}
	print("</table>");
	print("<br />");
	print("<br />");
}
///////////////////// Moderator Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For Moderator Only"] ?? 'For Moderator Only') . "  ::..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$modPanels = \Nexus\Database\NexusDB::table('modpanel')->get();
	foreach ($modPanels as $panelRow) {
		$row = (array) $panelRow;
		$id = $row['id'];
		$name =  $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}

	print("</table>");
	print("<br />");
	print("<br />");
}
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
