<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
	\App\Support\LegacyResponse::abort("Error", "Permission denied.");
$agents = \Nexus\Database\NexusDB::table('peers')
    ->selectRaw('agent, count(*) as counts')
    ->groupBy('agent')
    ->orderBy('agent')
    ->get();
\App\Support\Html::stdhead("All Clients");
print("<table align=center border=3 cellspacing=0 cellpadding=5>\n");
print("<tr><td class=colhead>Client</td><td class=colhead>Counts</td></tr>\n");
foreach ($agents as $row) {
	$arr2 = (array) $row;
	print("</a></td><td align=left>{$arr2['agent']}</td><td align=left>{$arr2['counts']}</td></tr>\n");
}
print("</table>\n");
\App\Support\Html::stdfoot();
