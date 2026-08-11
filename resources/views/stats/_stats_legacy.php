<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
	\App\Support\LegacyResponse::abort("Error", "Permission denied.");

\App\Support\Html::stdhead("Stats");
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
\App\Support\Frame::mainFrameOpen();

$n_tor = \Nexus\Database\NexusDB::table('torrents')->count();
$n_peers = \Nexus\Database\NexusDB::table('peers')->count();

$uporder = \App\Support\SupportContext::getQuery('uporder') ?? '';
$catorder = \App\Support\SupportContext::getQuery("catorder") ?? '';

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
	\App\Support\Html::stdMessage("Sorry...", "No uploaders.");
else
{
	\App\Support\Html::beginFrame("Uploader Activity", True);
	\App\Support\Html::beginTable();
	print("<tr>\n
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=uploader&catorder=$catorder\" class=colheadlink>Uploader</a></td>\n
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=lastul&catorder=$catorder\" class=colheadlink>Last Upload</a></td>\n
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=torrents&catorder=$catorder\" class=colheadlink>Torrents</a></td>\n
	<td class=colhead>Perc.</td>\n
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=peers&catorder=$catorder\" class=colheadlink>Peers</a></td>\n
	<td class=colhead>Perc.</td>\n
	</tr>\n");
	foreach ($upers as $uper)
	{
		$uper = (array) $uper;
		print("<tr><td>" . \App\Support\UserDisplay::username($uper['id']) . "</td>\n");
		print("<td " . ($uper['last']?(">".$uper['last']." (".\App\Support\Format::getElapsedTime(strtotime($uper['last']))." ago)"):"align=center>---") . "</td>\n");
		print("<td align=right>" . $uper['n_t'] . "</td>\n");
		print("<td align=right>" . ($n_tor > 0?number_format(100 * $uper['n_t']/$n_tor,1)."%":"---") . "</td>\n");
		print("<td align=right>" . $uper['n_p']."</td>\n");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $uper['n_p']/$n_peers,1)."%":"---") . "</td></tr>\n");
	}
	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
}

if ($n_tor == 0)
	\App\Support\Html::stdMessage("Sorry...", "No categories defined!");
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

	\App\Support\Html::beginFrame("Category Activity", True);
	\App\Support\Html::beginTable();
	print("<tr><td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=$uporder&catorder=category\" class=colheadlink>Category</a></td>
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=$uporder&catorder=lastul\" class=colheadlink>Last Upload</a></td>
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=$uporder&catorder=torrents\" class=colheadlink>Torrents</a></td>
	<td class=colhead>Perc.</td>
	<td class=colhead><a href=\"" . $__server_PHP_SELF . "?uporder=$uporder&catorder=peers\" class=colheadlink>Peers</a></td>
	<td class=colhead>Perc.</td></tr>\n");
	foreach ($cats as $cat)
	{
		$cat = (array) $cat;
		print("<tr><td class=rowhead>" . $cat['name'] . "</b></a></td>");
		print("<td " . ($cat['last']?(">".$cat['last']." (".\App\Support\Format::getElapsedTime(strtotime($cat['last']))." ago)"):"align = center>---") ."</td>");
		print("<td align=right>" . $cat['n_t'] . "</td>");
		print("<td align=right>" . number_format(100 * $cat['n_t']/$n_tor,1) . "%</td>");
		print("<td align=right>" . $cat['n_p'] . "</td>");
		print("<td align=right>" . ($n_peers > 0?number_format(100 * $cat['n_p']/$n_peers,1)."%":"---") . "</td>\n");
	}
	\App\Support\Html::endTable();
	\App\Support\Html::endFrame();
}

\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
return;
