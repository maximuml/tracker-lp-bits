<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function bark($msg)
{
$lang_takeflush = (array) (\App\Support\SupportContext::getGlobal('lang_takeflush') ?? []);
   if (empty($nexus_legacy_layout)) { stdhead(); }
   stdmsg($lang_takeflush['std_failed'], $msg);
   if (empty($nexus_legacy_layout)) { stdfoot(); }
   return;
}

$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
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
