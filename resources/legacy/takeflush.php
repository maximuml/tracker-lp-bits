<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_takeflush)) $lang_takeflush = (array) (\App\Support\SupportContext::getGlobal('lang_takeflush') ?? []);
?>
<?php
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
   \App\Support\LegacyResponse::abort($lang_takeflush['std_failed'] ?? 'Failed', $lang_takeflush['std_cannot_flush_others']);
}
?>
