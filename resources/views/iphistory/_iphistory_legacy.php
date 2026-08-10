<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO);

$userid = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
if (!is_valid_id($userid))
	stderr($lang_iphistory['std_error'], $lang_iphistory['std_invalid_id']);

$username = \App\Models\User::query()->where('id', $userid)->value('username');
if (!$username)
	stderr($lang_iphistory['error'], $lang_iphistory['text_user_not_found']);

$perpage = 20;

$iplogDistinct = \Nexus\Database\NexusDB::table('iplog')->where('userid', $userid)->distinct('access')->count('access');
$countrows = $iplogDistinct + 1;
$order = \App\Support\SupportContext::getQuery('order') ?? '';

list($pagertop, $pagerbottom, , $offset, $perpage) = pager($perpage, $countrows, "iphistory.php?id=$userid&order=$order&");

$userHistory = \Nexus\Database\NexusDB::table('users as u')
    ->select('u.id', 'u.ip as ip', 'last_access as access')
    ->where('u.id', $userid);
$ipLogHistory = \Nexus\Database\NexusDB::table('iplog')
    ->select('iplog.userid as id', 'iplog.ip as ip', 'iplog.access as access')
    ->where('iplog.userid', $userid);
$rows = $userHistory->union($ipLogHistory)
    ->orderBy('access', 'desc')
    ->limit($perpage)
    ->offset($offset)
    ->get();

stdhead($lang_iphistory['head_ip_history_log_for'].$username);
begin_main_frame();

print("<h1 align=\"center\">".$lang_iphistory['text_historical_ip_by'] . get_username($userid)."</h1>");

if ($countrows > $perpage)
echo $pagertop;

print("<table width=500 border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr>\n
<td class=colhead>".$lang_iphistory['col_last_access']."</td>\n
<td class=colhead>".$lang_iphistory['col_ip']."</td>\n
<td class=colhead>".$lang_iphistory['col_hostname']."</td>\n
</tr>\n");
foreach ($rows as $row) {
    $arr = (array) $row;
    $addr = "";
    $ipshow = "";
    if ($arr["ip"])
    {
        $ip = $arr["ip"];
        $dom = @gethostbyaddr($arr["ip"]);
        if ($dom == $arr["ip"] || @gethostbyname($dom) != $arr["ip"])
            $addr = $lang_iphistory['text_not_available'];
        else
            $addr = $dom;

        $usersIp = \Nexus\Database\NexusDB::table('users')->where('ip', $ip)->pluck('id')->all();
        $iplogIp = \Nexus\Database\NexusDB::table('iplog')->where('ip', $ip)->pluck('userid')->all();
        $ipcount = count(array_unique(array_merge($usersIp, $iplogIp)));

        if ($ipcount > 1)
            $ipshow = "<a href=\"ipsearch.php?ip=". $arr['ip'] ."\">" . $arr['ip'] ."</a> <b>(<font class='striking'>".$lang_iphistory['text_duplicate']."</font>)</b>";
        else
            $ipshow = "<a href=\"ipsearch.php?ip=". $arr['ip'] ."\">" . $arr['ip'] ."</a>";
    }
    $date = gettime($arr["access"]);
    print("<tr><td>".$date."</td>\n");
    print("<td>".$ipshow."</td>\n");
    print("<td>".$addr."</td></tr>\n");
}

print("</table>");

echo $pagerbottom;

end_main_frame();
stdfoot();
die;
?>
