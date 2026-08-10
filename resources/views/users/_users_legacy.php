<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::VIEW_USER_LIST);
$search = trim(is_scalar(\App\Support\SupportContext::getQuery('search') ?? '') ? (string) (\App\Support\SupportContext::getQuery('search') ?? '') : '');
$class = is_scalar(\App\Support\SupportContext::getQuery('class') ?? '') ? (string) (\App\Support\SupportContext::getQuery('class') ?? '-') : '-';
$country = intval(is_scalar(\App\Support\SupportContext::getQuery('country') ?? '') ? (string) (\App\Support\SupportContext::getQuery('country') ?? '0') : '0');
$letter = trim(is_scalar(\App\Support\SupportContext::getQuery("letter") ?? '') ? (string) (\App\Support\SupportContext::getQuery("letter") ?? '') : '');

if (strlen($letter) > 1)
	return;
$q = '';

if(!is_valid_user_class($class))
	$class = '-';

if ($search != '' && $letter == '')
{
	$q = "search=" . rawurlencode($search);
}
elseif ($letter != '' && strpos("0abcdefghijklmnopqrstuvwxyz", $letter) == true)
{
  $q = "letter=$letter";
}

if ($class != '-')
{
	$q .= ($q ? "&" : "") . "class=$class";
}

if ($country > 0)
{
	$q .= ($q ? "&" : "") . "country=$country";
}
stdhead($lang_users['head_users']);

print($lang_users['text_users']);

print("<form method=get action=?>\n");
print($lang_users['text_search'] ." <input type=text style=\"width:100px\" name=search value=$search> \n");
print("<select name=class>\n");
print("<option value='-'>".$lang_users['select_any_class']."</option>\n");
for ($i = 0;;++$i)
{
	if ($c = get_user_class_name($i,false,true,true))
		print("<option value=$i" . ($class != '-' && $class == $i ? " selected" : "") . ">$c</option>\n");
	else
		break;
}
print("</select>\n");
$countries = "<option value=0>".$lang_users['select_any_country']."</option>\n";
foreach (\App\Repositories\UserListingRepository::getCountries() as $ct_a)
	$countries .= "<option value=".htmlspecialchars((string)$ct_a['id']).">".htmlspecialchars($ct_a['name'])."</option>\n";
print("<select name=country>".$countries."</select>");
print("<input type=submit value=\"".$lang_users['submit_okay']."\">\n");
print("</form>\n");

print("<p>\n");

for ($i = 97; $i < 123; ++$i)
{
	$l = chr($i);
	$L = chr($i - 32);
	//stderr("",$class);
	if ($l == $letter)
		print("<font class=gray><b>$L</b></font>\n");
	else
	{
		if($class == '-')
			print("<a href=?letter=$l".($country > 0 ? "&country=".$country : "")."><b>$L</b></a>\n");
		else
		{
			print("<a href=?letter=$l&class=$class".($country > 0 ? "&country=".$country : "")."><b>$L</b></a>\n");
		}
	}
}

print("</p>\n");

$perpage = 50;

$filters = ['search' => $search, 'class' => $class, 'country' => $country, 'letter' => $letter];
$count = \App\Repositories\UserListingRepository::countUsers($filters);

list($pagertop, $pagerbottom, $limit, $offset) = pager($perpage, $count, "users.php?".$q.($q ? "&" : ""));

print($pagertop);

$userRows = \App\Repositories\UserListingRepository::listUsers($filters, (int)$offset, $perpage);

print("<table border=1 cellspacing=0 cellpadding=5>\n");
print("<tr><td class=colhead align=left>".$lang_users['col_user_name']."</td><td class=colhead>".$lang_users['col_registered']."</td><td class=colhead>".$lang_users['col_last_access']."</td><td class=colhead align=left>".$lang_users['col_class']."</td><td class=colhead>".$lang_users['col_country']."</td></tr>\n");
foreach ($userRows as $arr)
{
print("<tr><td align=left>".get_username($arr['id'])."</td><td>".gettime($arr['added'], true, false)."</td><td>".gettime($arr['last_access'],true,false)."</td><td align=left>". get_user_class_name($arr['class'],false,true,true) . "</td><td align=center>".$arr['country']."</td></tr>");
}

print("</table>");
print($pagerbottom);

stdfoot();
return;