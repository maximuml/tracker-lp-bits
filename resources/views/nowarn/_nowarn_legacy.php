<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function bark($msg) {
\App\Support\Html::stdhead();
\App\Support\Html::stdMessage("Update Has Failed !", $msg);
\App\Support\Html::stdfoot();
exit;
}

if(((\App\Support\SupportContext::getPost("nowarned") !== null))&&(\App\Support\SupportContext::getPost("nowarned")=="nowarned")){
//if (get_user_class() >= UC_SYSOP) {
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
\App\Support\LegacyResponse::abort("Sorry", "Access denied.");
{
if (empty(\App\Support\SupportContext::getPost("usernw")) && empty(\App\Support\SupportContext::getPost("desact")) && empty(\App\Support\SupportContext::getPost("delete")))
   bark("You Must Select A User To Edit.");

if (!empty(\App\Support\SupportContext::getPost("usernw")))
{
//$msg = sqlesc("Your Warning Has Been Removed By: " . $CURUSER['username'] . ".");
//$added = sqlesc(date("Y-m-d H:i:s"));
//$userid = implode(", ", \App\Support\SupportContext::getPost('usernw'));
////sql_query("INSERT INTO messages (sender, receiver, msg, added) VALUES (0, $userid, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//
//$r = sql_query("SELECT modcomment FROM users WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('usernw')) . ")")or sqlerr(__FILE__, __LINE__);
//$user = mysql_fetch_array($r);
//$exmodcomment = $user["modcomment"];
//$modcomment = date("Y-m-d") . " - Warning Removed By " . $CURUSER['username'] . ".\n". $modcomment . $exmodcomment;
//sql_query("UPDATE users SET modcomment=" . sqlesc($modcomment) . " WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('usernw')) . ")") or sqlerr(__FILE__, __LINE__);
//
//$do="UPDATE users SET warned='no', warneduntil=null WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('usernw')) . ")";
//$res=sql_query($do);

$modcomment = date("Y-m-d") . " - Warning Removed By " . $CURUSER['username'];
\App\Models\User::query()->whereIn('id', \App\Support\SupportContext::getPost('usernw'))
    ->update([
        'warned' => 'no',
        'warneduntil' => null,
        'modcomment' => \Nexus\Database\NexusDB::raw("if(modcomment = '', '$modcomment', concat_ws('\n', '$modcomment', modcomment))")
    ]);
}

if (!empty(\App\Support\SupportContext::getPost("desact"))){
//$do="UPDATE users SET enabled='no' WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('desact')) . ")";
//$res=sql_query($do);
    \App\Models\User::query()->whereIn('id', \App\Support\SupportContext::getPost('desact'))->update(['enabled' => 'no']);
}
}
}
header("Location: warned.php");
return;
