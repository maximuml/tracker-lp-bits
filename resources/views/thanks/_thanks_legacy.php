<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



if (isset($_GET['id']))
	stderr("Party is over!", "This trick doesn't work anymore. You need to click the button!");
$userid = $CURUSER["id"];
$torrentid = $_POST["id"];
$torrentowner = \App\Models\Torrent::query()->where('id', $torrentid)->value('owner');
if (!$torrentowner)
	stderr("Error", "Invalid torrent id!");
$t_ab = \Nexus\Database\NexusDB::table('thanks')->where('torrentid', $torrentid)->where('userid', $userid)->count();
if ($t_ab != 0)
	stderr("Error", "You already said thanks!");
if (isset($userid) && isset($torrentid))
{
\Nexus\Database\NexusDB::table('thanks')->insert([
    'torrentid' => $torrentid,
    'userid' => $userid,
]);
KPS("+",$saythanks_bonus,$CURUSER['id']);//User gets bonus for saying thanks
KPS("+",$receivethanks_bonus,$torrentowner);//Thanks receiver get bonus
}
