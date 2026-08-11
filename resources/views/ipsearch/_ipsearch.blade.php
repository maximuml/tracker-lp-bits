<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO))
	\App\Support\LegacyResponse::permissionDenied();
else
{
	$ip = htmlspecialchars(trim(\App\Support\SupportContext::getQuery('ip')));
	if ($ip)
	{
		$regex = "/^(((1?\d{1,2})|(2[0-4]\d)|(25[0-5]))(\.\b|$)){4}$/";
		if (!filter_var($ip, FILTER_VALIDATE_IP))
		{
			\App\Support\LegacyResponse::abort($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_ip']);
		}
	}

	$mask = trim(\App\Support\SupportContext::getQuery('mask') ?? '');
	$singleIp = ($mask == "" || $mask == "255.255.255.255");
	if ($singleIp)
	{
		$dom = @gethostbyaddr($ip);
		if ($dom == $ip || @gethostbyname($dom) != $ip)
			$addr = "";
		else
			$addr = $dom;
	}
	else
	{
		if (substr($mask,0,1) == "/")
		{
			$n = substr($mask, 1, strlen($mask) - 1);
				if (!is_numeric($n) or $n < 0 or $n > 32)
				{
					\App\Support\LegacyResponse::abort($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_subnet_mask']);
				}
				else
					$mask = long2ip(pow(2,32) - pow(2,32-$n));
		}
		elseif (!preg_match($regex, $mask))
		{
			\App\Support\LegacyResponse::abort($lang_ipsearch['std_error'], $lang_ipsearch['std_invalid_subnet_mask']);
		}
		$addr = "Mask: $mask";
	}

	$applyUserIp = function ($query) use ($ip, $mask, $singleIp) {
		if ($singleIp) {
			$query->where('u.ip', $ip);
		} else {
			$query->whereRaw('INET_ATON(u.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
		}
	};
	$applyIplogIp = function ($query) use ($ip, $mask, $singleIp) {
		if ($singleIp) {
			$query->where('iplog.ip', $ip);
		} else {
			$query->whereRaw('INET_ATON(iplog.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
		}
	};

	\App\Support\Html::stdhead($lang_ipsearch['head_search_ip_history']);
	\App\Support\Frame::mainFrameOpen();

	print("<h1 align=\"center\">".$lang_ipsearch['text_search_ip_history']."</h1>\n");
	print("<form method=\"get\" action=\"\">");
	print("<table align=center border=1 cellspacing=0 width=115 cellpadding=5>\n");
	\App\Support\Html::tr($lang_ipsearch['row_ip']."<font color=red>*</font>", "<input type=\"text\" name=\"ip\" size=\"40\" value=\"".htmlspecialchars($ip)."\" />", 1);
	\App\Support\Html::tr("<nobr>".$lang_ipsearch['row_subnet_mask']."</nobr>", "<input type=\"text\" name=\"mask\" size=\"40\" value=\"" . htmlspecialchars($mask) . "\" />", 1);
	print("<tr><td align=\"right\" colspan=\"2\"><input type=\"submit\" value=\"".$lang_ipsearch['submit_search']."\"/></td></tr>");
	print("</table></form>\n");
	if ($ip)
	{
	$columns = ['u.id', 'u.username', 'u.ip as ip', 'u.ip as last_ip', 'u.last_access', 'u.last_access as access', 'u.email', 'u.invited_by', 'u.added', 'u.class', 'u.uploaded', 'u.downloaded', 'u.donor', 'u.enabled', 'u.warned'];
	$userQuery = \Nexus\Database\NexusDB::table('users as u')->select($columns);
	$applyUserIp($userQuery);

	$iplogQuery = \Nexus\Database\NexusDB::table('users as u')
		->rightJoin('iplog', 'u.id', '=', 'iplog.userid')
		->select($columns);
	$applyIplogIp($iplogQuery);
	$iplogQuery->groupBy('u.id');

	$union = $userQuery->union($iplogQuery);
	$unionSql = $union->toSql();

	$count = (int) \Nexus\Database\NexusDB::table(\Nexus\Database\NexusDB::raw("({$unionSql}) as ipsearch"))
		->mergeBindings($union)
		->selectRaw('count(DISTINCT id) as c')
		->value('c');

	if ($count == 0)
	{
		print("<p align=\"center\">".$lang_ipsearch['text_no_users_found']."</p>\n");
		\App\Support\Frame::mainFrameClose();
		\App\Support\Html::stdfoot();
		die;
	}

	$order = \App\Support\SupportContext::getQuery('order') ?? '';
	$page = intval(\App\Support\SupportContext::getQuery("page") ?? 0);
	$perpage = 20;

	list($pagertop, $pagerbottom, , $offset, $rpp) = \App\Support\Pagination::pager($perpage, $count, "{$__server_PHP_SELF}?ip=$ip&mask=$mask&order=$order&");

	if ($order == "added")
		$orderby = "added DESC";
	elseif ($order == "username")
		$orderby = "UPPER(username) ASC";
	elseif ($order == "email")
		$orderby = "email ASC";
	elseif ($order == "last_ip")
		$orderby = "last_ip ASC";
	elseif ($order == "last_access")
		$orderby = "last_ip ASC";
	else
		$orderby = "access DESC";

	$users = \Nexus\Database\NexusDB::table(\Nexus\Database\NexusDB::raw("({$unionSql}) as ipsearch"))
		->mergeBindings($union)
		->select('*')
		->groupBy('id')
		->orderByRaw($orderby)
		->limit($rpp)
		->offset($offset)
		->get();

	print("<h1 align=\"center\">".$count.$lang_ipsearch['text_users_used_the_ip'].$ip."</h1>");

	print("<table width=".CONTENT_WIDTH." border=1 cellspacing=0 cellpadding=5 align=center>\n");
	print("<tr><td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=username\">".$lang_ipsearch['col_username']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=last_ip\">".$lang_ipsearch['col_last_ip']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=last_access\">".$lang_ipsearch['col_last_access']."</a></td>".
"<td class=colhead align=center>".$lang_ipsearch['col_ip_num']."</td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask\">".$lang_ipsearch['col_last_access_on']."</a></td>".
"<td class=colhead align=center><a class=colhead href=\"?ip=$ip&mask=$mask&order=added\">".$lang_ipsearch['col_added']."</a></td>".
"<td class=colhead align=center>".$lang_ipsearch['col_invited_by']."</td>");

	foreach ($users as $user) {
	    $user = (array) $user;
		if ($user['added'] == '0000-00-00 00:00:00' || $user['added'] == null)
			$added = $lang_ipsearch['text_not_available'];
		else $added = \App\Support\Time::format($user['added']);
		if ($user['last_access'] == '0000-00-00 00:00:00' || $user['added'] == null)
			$lastaccess = $lang_ipsearch['text_not_available'];
		else $lastaccess = \App\Support\Time::format($user['last_access']);

		if ($user['last_ip'])
			$ipstr = $user['last_ip'];
		else
			$ipstr = $lang_ipsearch['text_not_available'];

		$iphistory = \Nexus\Database\NexusDB::table('iplog')->where('userid', $user['id'])->distinct('ip')->count('ip');

		if ($user["invited_by"] > 0)
		{
			$invited_by = \App\Support\UserDisplay::username($user['invited_by']);
		}
		else
			$invited_by = $lang_ipsearch['text_not_available'];

		echo "<tr><td align=\"center\">" .
\App\Support\UserDisplay::username($user['id'])."</td>".
"<td align=\"center\">" . $ipstr . "</td>
<td align=\"center\">" . $lastaccess . "</td>
<td align=\"center\"><a href=\"iphistory.php?id=" . $user['id'] . "\">" . $iphistory. "</a></td>
<td align=\"center\">" . \App\Support\Time::format($user['access']) . "</td>
<td align=\"center\">" . \App\Support\Time::format($user['added']) . "</td>
<td align=\"center\">" . $invited_by . "</td>
</tr>\n";
	}
	echo "</table>";

	echo $pagerbottom;
	}
	\App\Support\Frame::mainFrameClose();
	\App\Support\Html::stdfoot();
}
?>
