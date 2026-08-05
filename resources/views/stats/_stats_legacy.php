<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (get_user_class() < UC_MODERATOR)
	stderr("Error", "Permission denied.");

stdhead("Stats");
?>

<STYLE TYPE="text/css" MEDIA=screen>
  a.colheadlink:link, a.colheadlink:visited{
	font-weight: bold;
	color: #FFFFFF;
	text-decoration: none;
	}

	a.colheadlink:hover {
  	text-decoration: underline;
	}
</STYLE>

<?php
begin_main_frame();

$n_tor = \Nexus\Database\NexusDB::table('torrents')->count();
$n_peers = \Nexus\Database\NexusDB::table('peers')->count();

$uporder = $_GET['uporder'] ?? '';
$catorder = $_GET["catorder"] ?? '';

if ($uporder == "lastul")
	$orderby = "last DESC, name";
elseif ($uporder == "torrents")
	$orderby = "n_t DESC, name";
elseif ($uporder == "peers")
	$orderby = "n_p DESC, name";
else
	$orderby = "name";

$uploaderQueryBase = \Nexus\Database\NexusDB::table('users as u')
    ->selectRaw('u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
    ->leftJoin('torrents as t', 'u.id', '=', 't.owner')
    ->leftJoin('peers as p', 't.id', '=', 'p.torrent');
$first = clone $uploaderQueryBase;
$first->where('u.class', 3)->groupBy('u.id');
$second = clone $uploaderQueryBase;
$second->where('u.class', '>', 3)->groupBy('u.id');
$upers = $first->union($second)->orderByRaw($orderby)->get();

if ($upers->isEmpty())
	stdmsg("Sorry...", "No uploaders.");
else
{
	begin_frame("Uploader Activity", True);
	begin_table();
	print("<tr>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=uploader&catorder=$catorder\" class=colheadlink>Uploader</a></td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=lastul&catorder=$catorder\" class=colheadlink>Last Upload</a></td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=torrents&catorder=$catorder\" class=colheadlink>Torrents</a></td>\n
	<td class=colhead>Perc.</td>\n
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=peers&catorder=$catorder\" class=colheadlink>Peers</a></td>\n
	<td class=colhead>Perc.</td>\n
	</tr>\n");
	foreach ($upers as $uper)
	{
		$uper = (array) $uper;
		print("<tr><td>" . get_username($uper['id']) . "</td>\n");
		print("<td " . ($uper['last']?(">".$uper['last']." (".get_elapsed_time(strtotime($uper['last']))." ago)"):"align=center>---") . "</td>\n");
		print("<td align=right>" . $uper['n_t'] . "</td>\n");
		print("<td align=right>" . ($n_tor > 0?number_format(100 * $uper['n_t']/$n_tor,1)."%":"---") . "</td>\n");
		print("<td align=right>" . $uper['n_p']."</td>\n");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $uper['n_p']/$n_peers,1)."%":"---") . "</td></tr>\n");
	}
	end_table();
	end_frame();
}

if ($n_tor == 0)
	stdmsg("Sorry...", "No categories defined!");
else
{
  if ($catorder == "lastul")
		$orderby = "last DESC, c.name";
	elseif ($catorder == "torrents")
		$orderby = "n_t DESC, c.name";
	elseif ($catorder == "peers")
		$orderby = "n_p DESC, name";
	else
		$orderby = "c.name";

  $cats = \Nexus\Database\NexusDB::table('categories as c')
    ->selectRaw('c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
    ->leftJoin('torrents as t', 't.category', '=', 'c.id')
    ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
    ->groupBy('c.id')
    ->orderByRaw($orderby)
    ->get();

	begin_frame("Category Activity", True);
	begin_table();
	print("<tr><td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=category\" class=colheadlink>Category</a></td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=lastul\" class=colheadlink>Last Upload</a></td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=torrents\" class=colheadlink>Torrents</a></td>
	<td class=colhead>Perc.</td>
	<td class=colhead><a href=\"" . $_SERVER['PHP_SELF'] . "?uporder=$uporder&catorder=peers\" class=colheadlink>Peers</a></td>
	<td class=colhead>Perc.</td></tr>\n");
	foreach ($cats as $cat)
	{
		$cat = (array) $cat;
		print("<tr><td class=rowhead>" . $cat['name'] . "</b></a></td>");
		print("<td " . ($cat['last']?(">".$cat['last']." (".get_elapsed_time(strtotime($cat['last']))." ago)"):"align = center>---") ."</td>");
		print("<td align=right>" . $cat['n_t'] . "</td>");
		print("<td align=right>" . number_format(100 * $cat['n_t']/$n_tor,1) . "%</td>");
		print("<td align=right>" . $cat['n_p'] . "</td>");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $cat['n_p']/$n_peers,1)."%":"---") . "</td>\n");
	}
	end_table();
	end_frame();
}

end_main_frame();
stdfoot();
return;
