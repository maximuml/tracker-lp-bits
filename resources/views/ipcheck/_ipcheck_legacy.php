<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (get_user_class() < UC_MODERATOR)
stderr("Sorry", "Access denied.");

$tabs = ['users', 'peers'];
$tab = 'users';
if (!empty(\App\Support\SupportContext::getRequestInput('tab')) && in_array(\App\Support\SupportContext::getRequestInput('tab'), $tabs)) {
    $tab = \App\Support\SupportContext::getRequestInput('tab');
}
$page = \App\Support\SupportContext::getRequestInput('page') ?? 0;
$title = 'Duplicate IP users';
stdhead($title);
print '<h1>'.$title.'</h1>';
//print '<ul class="menu" style="padding-inline-start: 0">';
//foreach ($tabs as $item) {
//    echo sprintf('<li class="%s"><a href="?tab=%s&page=%s">%s</a></li>', $tab == $item ? 'selected' : '', $item, $page, $item);
//}
//print '</ul>';
begin_table();

if (get_user_class() >= UC_MODERATOR || $CURUSER["guard"] == "yes")
{
 $res = \Nexus\Database\NexusDB::table('users')
     ->selectRaw('ip, count(*) AS dupl')
     ->where('enabled', 'yes')
     ->where('ip', '!=', '')
     ->where('ip', '!=', '127.0.0.0')
     ->groupBy('ip')
     ->orderByDesc('dupl')
     ->orderBy('ip')
     ->get();
  print("<tr align=center><td class=colhead width=90>User</td>
 <td class=colhead width=70>Email</td>
 <td class=colhead width=70>Registered</td>
 <td class=colhead width=75>Last access</td>
 <td class=colhead width=70>Downloaded</td>
 <td class=colhead width=70>Uploaded</td>
 <td class=colhead width=45>Ratio</td>
 <td class=colhead width=125>IP</td>
 <td class=colhead width=40>Peer</td></tr>\n");
 $uc = 0;
 $ip = '';
  foreach ($res as $row) {
	$ras = (array) $row;
	if ($ras["dupl"] <= 1)
	  break;
	if ($ip <> $ras['ip'])
    {
	  $users = \App\Models\User::query()
	      ->where('ip', $ras['ip'])
	      ->orderBy('id')
	      ->get(['id', 'username', 'email', 'added', 'last_access', 'downloaded', 'uploaded', 'ip', 'warned', 'donor', 'enabled']);
	  if ($users->count() > 1)
	  {
		$uc++;
	    foreach ($users as $userRow) {
	        $arr = $userRow->toArray();
		  if ($arr['added'] == '0000-00-00 00:00:00' || $arr['added'] == null)
			$arr['added'] = '-';
		  if ($arr['last_access'] == '0000-00-00 00:00:00' || $arr['last_access'] == null)
			$arr['last_access'] = '-';
		  if($arr["downloaded"] != 0)
			$ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
		  else
			$ratio="---";

		  $ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
		  $uploaded = mksize($arr["uploaded"]);
		  $downloaded = mksize($arr["downloaded"]);
		  $added = substr($arr['added'],0,10);
		  $last_access = substr($arr['last_access'],0,10);
		  if($uc%2 == 0)
			$utc = "";
		  else
			$utc = " bgcolor=\"ECE9D8\"";

			$peer_count = \Nexus\Database\NexusDB::table('peers')->where('ip', $ras['ip'])->where('userid', $arr['id'])->count();
		  print("<tr$utc><td align=left>" . get_username($arr["id"])."</td>
				  <td align=center>$arr[email]</td>
				  <td align=center>$added</td>
				  <td align=center>$last_access</td>
				  <td align=center>$downloaded</td>
				  <td align=center>$uploaded</td>
				  <td align=center>$ratio</td>
				  <td align=center><a href=\"http://www.whois.sc/$arr[ip]\" target=\"_blank\">$arr[ip]</a></td>\n<td align=center>" .
				  ($peer_count ? "ja" : "nein") . "</td></tr>\n");
		  $ip = $arr["ip"];
		}
	  }
	}
  }
}
else
{
 print("<br /><table width=60% border=1 cellspacing=0 cellpadding=9><tr><td align=center>");
 print("<h2>Sorry, only for Team</h2></table></td></tr>");
}
end_frame();
end_table();

stdfoot();
?>
