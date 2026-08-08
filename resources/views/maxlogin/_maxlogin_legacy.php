<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (get_user_class() < UC_SYSOP)
 stderr("Error", "Permission denied.");

$action = ((\App\Support\SupportContext::getPost('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('action')) : (((\App\Support\SupportContext::getQuery('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('action')) : 'showlist');
$id = ((\App\Support\SupportContext::getPost('id') !== null)) ? (int)\App\Support\SupportContext::getPost('id') : (((\App\Support\SupportContext::getQuery('id') !== null)) ? (int)\App\Support\SupportContext::getQuery('id') : 0);
$update = ((\App\Support\SupportContext::getPost('update') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('update')) : (((\App\Support\SupportContext::getQuery('update') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('update')) : '');

function check ($id) {
	if (!is_valid_id($id))
		stderr("Error","Invalid ID");
}
function searchform () {
?>
<form method=post name=search action=maxlogin.php?>
<input type=hidden name=action value=searchip>
<p class=success align=center>Search IP <input type=text name=ip size=25> <input type=submit name=submit value='Search IP' class=btn></p>
</form>
<?php
}
$countrows = \Nexus\Database\NexusDB::table('loginattempts')->count() + 1;
$page = intval(\App\Support\SupportContext::getQuery("page") ?? 0);

$order = \App\Support\SupportContext::getQuery('order') ?? '';
$orderColumn = match ($order) {
    'ip' => 'ip',
    'added' => 'added',
    'attempts' => 'attempts',
    'type' => 'type',
    'status' => 'banned',
    default => 'id',
};

$perpage = 50;
list($pagertop, $pagerbottom, , $offset, $perpage) = pager($perpage, $countrows, "maxlogin.php?order=$order&");
$msg = '';
if ($update) {
    $msg = "<h3><b>".htmlspecialchars($update)." Successful!</b></h3>";
}
if ($action == 'showlist') {
stdhead ("Max. Login Attemps - Show List");
print("<h1>Failed Login Attempts</h1>");
print($msg);
print("<table border=1 cellspacing=0 cellpadding=5 width=100%>\n");

$loginAttempts = \Nexus\Database\NexusDB::table('loginattempts')->orderByDesc($orderColumn)->offset($offset)->limit($perpage)->get();
if ($loginAttempts->isEmpty())
	  print("<tr><td colspan=2><b>Nothing found</b></td></tr>\n");
else
{
  print("<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>".
    "<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n");

  foreach ($loginAttempts as $attemptRow)
  {
  	$arr = (array) $attemptRow;
  	$user = \App\Models\User::query()->where('ip', $arr['ip'])->first(['id', 'username']);
  	$a2 = $user ? $user->toArray() : [];
 	  print("<tr><td align=>{$arr['id']}</td><td align=left>{$arr['ip']} " . ($a2['id'] ? get_username($a2['id']) : "" ) . "</td><td align=left>{$arr['added']}</td><td align=left>$arr[attempts]</td><td align=left>".($arr['type'] == "recover" ? "Recover Password Attempt!" : "Login Attempt!")."</td><td align=left>".($arr['banned'] == "yes" ? "<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id={$arr['id']}><font color=green>[<b>unban</b>]</font></a>" : "<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id={$arr['id']}><font color=red>[<b>ban</b>]</font></a>")."  <a OnClick=\"return confirm('Are you wish to delete this attempt?');\" href=maxlogin.php?action=delete&id={$arr['id']}>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id={$arr['id']}><font color=blue>[<b>edit</b></a>]</font></td></tr>\n");
  }

}
print("</table>");
if ($countrows > $perpage) {
    echo $pagerbottom;
}
searchform();
stdfoot();
}elseif ($action == 'ban') {
	check($id);
	stdhead ("Max. Login Attemps - BAN");
	\Nexus\Database\NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'yes']);
	header("Location: maxlogin.php?update=Ban");
	return;
}elseif ($action == 'unban') {
	check($id);
	stdhead ("Max. Login Attemps - UNBAN");
	\Nexus\Database\NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'no']);
	header("Location: maxlogin.php?update=Unban");
	return;
}elseif ($action == 'delete') {
	check($id);
	stdhead ("Max. Login Attemps - DELETE");
	\Nexus\Database\NexusDB::table('loginattempts')->where('id', $id)->delete();
	header("Location: maxlogin.php?update=Delete");
	return;
}elseif ($action == 'edit') {
	check($id);
	stdhead ("Max. Login Attemps - EDIT (".htmlspecialchars($id).")");
	$a = (array) \Nexus\Database\NexusDB::table('loginattempts')->where('id', $id)->first();
	print("<table border=1 cellspacing=0 cellpadding=5 width=100%>\n");
	print("<tr><td><p>IP Address: <b>".htmlspecialchars($a['ip'])."</b></p>");
	print("<p>Action Time: <b>".htmlspecialchars($a['added'])."</b></p></tr></td>");
	print("<form method='post' action='maxlogin.php'>");
	print("<input type='hidden' name='action' value='save'>");
	print("<input type='hidden' name='id' value='{$a['id']}'>");
	print("<input type='hidden' name='ip' value='{$a['ip']}'>");
	if (\App\Support\SupportContext::getQuery('return') == 'yes')
		print("<input type='hidden' name='returnto' value='viewunbaniprequest.php'>");
	print("<tr><td>Attempts <input type='text' size='33' name='attempts' value='{$a['attempts']}'>");
	print("<tr><td>Attempt Type <select name='type'><option value='login' ".($a["type"] == "login" ? "selected" : "").">Login Attempt</option><option value='recover' ".($a["type"] == "recover" ? "selected" : "").">Recover Password Attempts</option></select></tr></td>");
	print("<tr><td>Current Status <select name='banned'><option value='yes' ".($a["banned"] == "yes" ? "selected" : "").">Banned!</option><option value='no' ".($a["banned"] == "no" ? "selected" : "").">Not Banned!</option></select></tr></td>");
	print("<tr><td><input type='submit' name='submit' value='Save' class=btn></tr></td>");
	print("</table>");
	stdfoot();

}elseif ($action == 'save') {
	$id = intval(\App\Support\SupportContext::getPost('id') ?? 0);
	$attempts = (int)\App\Support\SupportContext::getPost('attempts');
	$type = \App\Support\SupportContext::getPost('type');
	$banned = \App\Support\SupportContext::getPost('banned');
		check($id);
	if (!is_numeric($attempts) || $attempts < 0)
		stderr("Error", "Invalid attempts");
	\Nexus\Database\NexusDB::table('loginattempts')->where('id', $id)->update([
	    'attempts' => $attempts,
	    'type' => $type,
	    'banned' => $banned,
	]);
	if (\App\Support\SupportContext::getPost('returnto')){
		$returnto = \App\Support\SupportContext::getPost('returnto');
		header("Location: $returnto");
	}
	else
		header("Location: maxlogin.php?update=Edit");
	return;
}elseif ($action == 'searchip') {
	$ip = \App\Support\SupportContext::getPost('ip') ?? '';
	$search = \Nexus\Database\NexusDB::table('loginattempts')->where('ip', 'LIKE', "%$ip%")->get();
	stdhead ("Max. Login Attemps - Search");
	print("<h2>Failed Login Attempts</h2>");
	print("<table border=1 cellspacing=0 cellpadding=5 width=100%>\n");
	if ($search->isEmpty())
	  print("<tr><td colspan=2><b>Sorry, nothing found!</b></td></tr>\n");
	else
		{
 			print("<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>".
    		"<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n");

			foreach ($search as $attemptRow)
				  {
				  	$arr = (array) $attemptRow;
				  	$user = \App\Models\User::query()->where('ip', $arr['ip'])->first(['id', 'username']);
				  	$a2 = $user ? $user->toArray() : [];
				 	print("<tr><td align=>{$arr['id']}</td><td align=left>{$arr['ip']} " . ($a2['id'] ? get_username($a2['id']) : "" ) . "</td><td align=left>{$arr['added']}</td><td align=left>$arr[attempts]</td><td align=left>".($arr['type'] == "recover" ? "Recover Password Attempt!" : "Login Attempt!")."</td><td align=left>".($arr['banned'] == "yes" ? "<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id={$arr['id']}><font color=green>[<b>unban</b>]</font></a>" : "<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id={$arr['id']}><font color=red>[<b>ban</b>]</font></a>")."  <a OnClick=\"return confirm('Are you wish to delete this attempt?');\" href=maxlogin.php?action=delete&id={$arr['id']}>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id={$arr['id']}><font color=blue>[<b>edit</b></a>]</font></td></tr>\n");
				  }
	}
	print("</table>\n");
	searchform();
	stdfoot();
}
else
	stderr("Error","Invalid Action");
