<?php
require_once("../include/bittorrent.php");
dbconn();
$langFile = get_langfile_path();
if (!is_file(__DIR__ . '/../' . $langFile)) {
	$langFile = __DIR__ . '/../lang/en/lang_takeflush.php';
}
require_once $langFile;
loggedinorreturn();
function bark($msg)
{
   global $lang_takeflush;
   stdhead();
   stdmsg($lang_takeflush['std_failed'], $msg);
   stdfoot();
   exit;
}

$id = intval($_GET['id'] ?? 0);
int_check($id,true);

if (get_user_class() >= UC_MODERATOR || $CURUSER['id'] == "$id")
{
   $deadtime = deadtime();
   $lastAction = date("Y-m-d H:i:s", $deadtime);
   $effected = \App\Models\Peer::query()->where('last_action', '<', $lastAction)->where('userid', $id)->delete();

   stderr($lang_takeflush['std_success'], "$effected ".$lang_takeflush['std_ghost_torrents_cleaned']);
}
else
{
   bark($lang_takeflush['std_cannot_flush_others']);
}
