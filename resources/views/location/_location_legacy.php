<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
if (get_user_class() < UC_SYSOP) {
	echo "access denied.";
	return;
}
stdhead("Manage Locations");
begin_main_frame("",false,100);
begin_frame("Manage Locations",true,10,"100%","center");

$sure = \App\Support\SupportContext::getQuery('sure') ?? '';
if($sure == "yes") {
	$delid = (int)\App\Support\SupportContext::getQuery('delid');
	\Nexus\Database\NexusDB::table('locations')->where('id', $delid)->delete();
	echo("Location successfuly removed, click <a class=altlink href=" . $__server_REQUEST_URI .">here</a> to go back.");
	end_frame();
	stdfoot();
	return;
}
$delid = intval(\App\Support\SupportContext::getQuery('delid') ?? 0);
if($delid > 0) {
	echo("Are you sure you would like to delete this Location?( <strong><a href='". $__server_REQUEST_URI . "?delid=$delid&sure=yes'>Yes!</a></strong> / <strong><a href='". $__server_REQUEST_URI . "'>No</a></strong> )");
	end_frame();
	stdfoot();
	return;
}

$edited = intval(\App\Support\SupportContext::getQuery('edited') ?? 0);
if($edited == 1) {
	$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
	$name = \App\Support\SupportContext::getQuery('name');
	$flagpic = \App\Support\SupportContext::getQuery('flagpic');
	$location_main = \App\Support\SupportContext::getQuery('location_main');
	$location_sub = \App\Support\SupportContext::getQuery('location_sub');
	$start_ip = \App\Support\SupportContext::getQuery('start_ip');
	$end_ip = \App\Support\SupportContext::getQuery('end_ip');
	$theory_upspeed = \App\Support\SupportContext::getQuery('theory_upspeed');
	$practical_upspeed = \App\Support\SupportContext::getQuery('practical_upspeed');
	$theory_downspeed = \App\Support\SupportContext::getQuery('theory_downspeed');
	$practical_downspeed = \App\Support\SupportContext::getQuery('practical_downspeed');

	if(validip_format($start_ip) && validip_format($end_ip))
	{
		if(ip2long($end_ip) > ip2long($start_ip))
		{
			\Nexus\Database\NexusDB::table('locations')->where('id', $id)->update([
			    'name' => $name,
			    'flagpic' => $flagpic,
			    'location_main' => $location_main,
			    'location_sub' => $location_sub,
			    'start_ip' => $start_ip,
			    'end_ip' => $end_ip,
			    'theory_upspeed' => $theory_upspeed,
			    'practical_upspeed' => $practical_upspeed,
			    'theory_downspeed' => $theory_downspeed,
			    'practical_downspeed' => $practical_downspeed,
			]);
			stdmsg("Success!","Location has been edited, click <a class=altlink href=" . $__server_REQUEST_URI .">here</a> to go back");
			stdfoot();
			return;
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");

}

$editid = \App\Support\SupportContext::getQuery('editid') ?? 0;
if($editid > 0) {
	$row = (array) \Nexus\Database\NexusDB::table('locations')->where('id', $editid)->first();
	if (!$row) {
		echo("<p><strong>Location not found.</strong></p>");
	} else {
		$name = $row['name'];
		$flagpic = $row['flagpic'];
		$location_main = $row['location_main'];
		$location_sub = $row['location_sub'];
		$start_ip = $row['start_ip'];
		$end_ip = $row['end_ip'];
		$theory_upspeed = $row['theory_upspeed'];
		$practical_upspeed = $row['practical_upspeed'];
		$theory_downspeed = $row['theory_downspeed'];
		$practical_downspeed = $row['practical_downspeed'];

		echo("<form name='form1' method='get' action='" . $__server_REQUEST_URI . "'>'");
		echo("<input type='hidden' name='id' value='$editid'><table class=main cellspacing=0 cellpadding=5 width=50%>");
		echo("<tr><td class=colhead align=center colspan=2>Editing Locations</td><input type='hidden' name='edited' value='1'></tr>");
		echo("<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name' value='$name'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main' value='$location_main'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub' value='$location_sub'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip' value='" . $start_ip . "'></td></tr>");
		echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip' value='" . $end_ip. "'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed' value='$theory_upspeed'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed' value='$theory_downspeed'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed' value='$practical_upspeed'></td></tr>");
		echo("<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed' value='$practical_downspeed'></td></tr>");
		echo("<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic' value='$flagpic'></td></tr>");
		echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
		echo("</table></form>");
	}
	end_frame();
	stdfoot();
	return;
}

$add = \App\Support\SupportContext::getQuery('add') ?? '';
$success = false;
if($add == 'true') {
	$name = \App\Support\SupportContext::getQuery('name');
	$flagpic = \App\Support\SupportContext::getQuery('flagpic');
	$location_main = \App\Support\SupportContext::getQuery('location_main');
	$location_sub = \App\Support\SupportContext::getQuery('location_sub');
	$start_ip = \App\Support\SupportContext::getQuery('start_ip');
	$end_ip = \App\Support\SupportContext::getQuery('end_ip');
	$theory_upspeed = \App\Support\SupportContext::getQuery('theory_upspeed');
	$practical_upspeed = \App\Support\SupportContext::getQuery('practical_upspeed');
	$theory_downspeed = \App\Support\SupportContext::getQuery('theory_downspeed');
	$practical_downspeed = \App\Support\SupportContext::getQuery('practical_downspeed');

	if(validip_format($start_ip) && validip_format($end_ip))
	{
		if(ip2long($end_ip) > ip2long($start_ip))
		{
			\Nexus\Database\NexusDB::table('locations')->insert([
			    'name' => $name,
			    'flagpic' => $flagpic,
			    'location_main' => $location_main,
			    'location_sub' => $location_sub,
			    'start_ip' => $start_ip,
			    'end_ip' => $end_ip,
			    'theory_upspeed' => $theory_upspeed,
			    'practical_upspeed' => $practical_upspeed,
			    'theory_downspeed' => $theory_downspeed,
			    'practical_downspeed' => $practical_downspeed,
			]);
			$success = true;
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");

}

echo("<form name='form1' method='get' action='" . $__server_REQUEST_URI . "'>");
echo("<table class=main cellspacing=0 cellpadding=5 width=48% align= left>");
echo("<tr><td class=colhead align=center colspan=2>Add New Locations</td></tr>");
echo("<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name'></td></tr>");
echo("<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main'></td></tr>");
echo("<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub'></td></tr>");
echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip'></td></tr>");
echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip'></td></tr>");
echo("<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed'></td></tr>");
echo("<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed'></td></tr>");
echo("<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic'><input type='hidden' name='add' value='true'></td></tr>");
echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
echo("</table>");
echo("</form>");

$range_start_ip = \App\Support\SupportContext::getQuery('range_start_ip') ?? '';
$range_end_ip = \App\Support\SupportContext::getQuery('range_end_ip') ?? '';

echo("<form name='form2' method='get' action='" . $__server_REQUEST_URI . "'>");
echo("<table class=main cellspacing=0 cellpadding=5 width=48% align=right>");
echo("<tr><td class=colhead align=center colspan=2>Check IP Range</td></tr>");
echo("<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_start_ip' value='" . $range_start_ip . "'></td></tr>");
echo("<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_end_ip' value='" . $range_end_ip . "'><input type='hidden' name='check_range' value='true'></td></tr>");
echo("<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>");
echo("</table>");
echo("</form>");

print("<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />");

$check_range = \App\Support\SupportContext::getQuery('check_range') ?? '';
$hasRangeFilter = false;
if($check_range == 'true') {
	if(validip_format($range_start_ip) && validip_format($range_end_ip))
	{
		if(ip2long($range_end_ip) > ip2long($range_start_ip))
		{
			$hasRangeFilter = true;
			echo("<p><strong>Conforming Locations:</strong></p>");
		}
		else
			echo("<p><strong>The end IP address should be larger than the start one, or equal for single IP check!</strong></p>");
	}
	else
		echo("<p><strong>Invalid IP Address Format !!! </strong></p>");
}
else
{
	echo("<p><strong>" .  ($success == true ? "(Updated!)" : "") . "Existing Locations:</strong></p>");
}

echo("<table class=main cellspacing=0 cellpadding=5>");
echo("<td class=colhead align=center><b>ID</b></td> <td class=colhead align=left><b>Name</b></td> <td class=colhead align=center><b>Pic</b></td> <td class=colhead align=center><b><nobr>Main Location</nobr></b></td> <td class=colhead align=center><b><nobr>Sub Location</nobr></b></td> <td class=colhead align=center><b>Start IP</b></td> <td class=colhead align=center><b>End IP</b></td> <td class=colhead align=center><b>T.U</b></td> <td class=colhead align=center><b>P.U</b></td>  <td class=colhead align=center><b>T.D</b></td> <td class=colhead align=center><b>P.D</b></td> <td class=colhead align=center><b>Edit</b></td><td class=colhead align=center><b>Delete</b></td>");

$perpage = 50;
$baseQuery = \Nexus\Database\NexusDB::table('locations')
    ->when($hasRangeFilter, function ($q) use ($range_start_ip, $range_end_ip) {
        $start = ip2long($range_start_ip);
        $end = ip2long($range_end_ip);
        $q->whereRaw("INET_ATON(start_ip) <= $start AND INET_ATON(end_ip) >= $end");
    });

$count = $baseQuery->count();
list($pagertop, $pagerbottom, , $offset, $perpage) = pager($perpage, $count, "location.php?");

$locations = (clone $baseQuery)
    ->orderBy('name')
    ->orderBy('start_ip')
    ->offset($offset)
    ->limit($perpage)
    ->get();

$maxlen_sub_location = 40;
foreach ($locations as $loc) {
	$row = (array) $loc;
	$id = $row['id'];
	$name = $row['name'];
	$flagpic = $row['flagpic'];
	$location_main = $row['location_main'];
	$location_sub = $row['location_sub'];
	$start_ip = $row['start_ip'];
	$end_ip = $row['end_ip'];
	$theory_upspeed = $row['theory_upspeed'];
	$practical_upspeed = $row['practical_upspeed'];
	$theory_downspeed = $row['theory_downspeed'];
	$practical_downspeed = $row['practical_downspeed'];

	$count_location_sub=strlen($location_sub);
	if($count_location_sub > $maxlen_sub_location)
		$location_sub=substr($location_sub, 0, $maxlen_sub_location) . "..";

	echo("<tr><td class=rowfollow align=center><strong>$id</strong></td>" .
	"<td class=rowfollow align=left><strong>$name</strong></td>" .
	"<td class=rowfollow align=center>" . ($flagpic != "" ? "<img src='" . get_protocol_prefix() . "$BASEURL/pic/location/$flagpic' border='0' />" : "-") . "</td>" .
	"<td class=rowfollow align=left>$location_main</td>" .
	"<td class=rowfollow align=left>$location_sub</td>" .
	"<td class=rowfollow align=left>" . $start_ip . "</td>" .
	"<td class=rowfollow align=left>" . $end_ip . "</td>" .
	"<td class=rowfollow align=left>$theory_upspeed</td>" .
	"<td class=rowfollow align=left>$practical_upspeed</td>" .
	"<td class=rowfollow align=left>$theory_downspeed</td>" .
	"<td class=rowfollow align=left>$practical_downspeed</td>" .
	"<td class=rowfollow align=center><a href='" . $__server_REQUEST_URI . "?editid=$id'>Edit</a></td>".
	"<td class=rowfollow align=center><a href='" . $__server_REQUEST_URI . "?delid=$id'>Remove</a></td>" .
	"</tr>");
}
print("</table>");
echo $pagerbottom;

end_frame();
end_frame();
stdfoot();

