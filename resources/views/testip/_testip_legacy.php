<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) \App\Support\LegacyResponse::abort("Error", "Permission denied");

if ($__server_REQUEST_METHOD == "POST")
	$ip = \App\Support\SupportContext::getPost("ip");
else
	$ip = \App\Support\SupportContext::getQuery("ip") ?? '';
if ($ip)
{
	$nip = ip2long($ip);
	if ($nip == -1)
	  \App\Support\LegacyResponse::abort("Error", "Bad IP.");
	$rows = \Nexus\Database\NexusDB::table('bans')->where('first', '<=', $nip)->where('last', '>=', $nip)->get();
	if ($rows->isEmpty())
	  \App\Support\LegacyResponse::abort("Result", "The IP address <b>". htmlspecialchars($ip) ."</b> is not banned.", false);
	else
	{
	  $banstable = "<table class=main border=0 cellspacing=0 cellpadding=5>\n" .
	    "<tr><td class=colhead>First</td><td class=colhead>Last</td><td class=colhead>Comment</td></tr>\n";
	  foreach ($rows as $row)
	  {
	    $arr = (array) $row;
	    $first = long2ip($arr["first"]);
	    $last = long2ip($arr["last"]);
	    $comment = htmlspecialchars($arr["comment"]);
	    $banstable .= "<tr><td>$first</td><td>$last</td><td>$comment</td></tr>\n";
	  }
	  $banstable .= "</table>\n";
	  \App\Support\LegacyResponse::abort("Result", "<table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>The IP address <b>". $ip ."</b> is banned:</td></tr></table><p>". $banstable ."</p>", false);
	}
}
\App\Support\Html::stdhead();

?>
<h1>Test IP address</h1>
<form method=post action=testip.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>IP address</td><td><input type=text name=ip></td></tr>
<tr><td colspan=2 align=center><input type=submit class=btn value='OK'></td></tr>
</form>
</table>

<?php
\App\Support\Html::stdfoot();
