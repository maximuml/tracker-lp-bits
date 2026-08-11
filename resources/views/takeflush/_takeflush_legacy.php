<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function bark($msg)
{
$lang_takeflush = (array) (\App\Support\SupportContext::getGlobal('lang_takeflush') ?? []);
   \App\Support\Html::stdhead();
   \App\Support\Html::stdMessage($lang_takeflush['std_failed'], $msg);
   \App\Support\Html::stdfoot();
   return;
}

$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
\App\Support\LegacyResponse::assertId($id, true);

if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR || $CURUSER['id'] == "$id")
{
   $deadtime = \App\Support\Time::deadThreshold(\App\Support\Config\SiteConfig::current()->main->anninterthree());
   $lastAction = date("Y-m-d H:i:s", $deadtime);
   $effected = \App\Models\Peer::query()->where('last_action', '<', $lastAction)->where('userid', $id)->delete();

   \App\Support\LegacyResponse::abort($lang_takeflush['std_success'], "$effected ".$lang_takeflush['std_ghost_torrents_cleaned']);
}
else
{
   bark($lang_takeflush['std_cannot_flush_others']);
}
