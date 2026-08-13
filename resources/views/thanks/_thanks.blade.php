<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



if (((\App\Support\SupportContext::getQuery('id') !== null)))
	\App\Support\LegacyResponse::abort("Party is over!", "This trick doesn't work anymore. You need to click the button!");
$userid = $CURUSER["id"];
$torrentid = \App\Support\SupportContext::getPost("id");
$torrentowner = \App\Models\Torrent::query()->where('id', $torrentid)->value('owner');
if (!$torrentowner)
	\App\Support\LegacyResponse::abort("Error", "Invalid torrent id!");
$t_ab = \Nexus\Database\NexusDB::table('thanks')->where('torrentid', $torrentid)->where('userid', $userid)->count();
if ($t_ab != 0)
	\App\Support\LegacyResponse::abort("Error", "You already said thanks!");
if ((isset($userid)) && (isset($torrentid)))
{
\Nexus\Database\NexusDB::table('thanks')->insert([
    'torrentid' => $torrentid,
    'userid' => $userid,
]);
\App\Support\Bonus::updatePoints((string) "+", (float) $saythanks_bonus, $CURUSER['id']);//User gets bonus for saying thanks
\App\Support\Bonus::updatePoints((string) "+", (float) $receivethanks_bonus, $torrentowner);//Thanks receiver get bonus
}
