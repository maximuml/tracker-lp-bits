<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
user_can('staffmem', true);
stdhead($lang_staff['head_staff']);

$Cache->new_page('staff_page', 900, true);
if (!$Cache->get_page()){
$Cache->add_whole_row();
begin_main_frame();
$secs = 900;
$dt = TIMENOW - $secs;
$onlineimg = "<img class=\"button_online\" src=\"pic/trans.gif\" alt=\"online\" title=\"".$lang_staff['title_online']."\" />";
$offlineimg = "<img class=\"button_offline\" src=\"pic/trans.gif\" alt=\"offline\" title=\"".$lang_staff['title_offline']."\" />";
$sendpmimg = "<img class=\"button_pm\" src=\"pic/trans.gif\" alt=\"pm\" />";
//--------------------- FIRST LINE SUPPORT SECTION ---------------------------//
$ppl = '';
$supportUsers = \App\Models\User::query()->where('support', 'yes')->where('status', 'confirmed')->orderBy('username')->get(['id', 'country', 'last_access', 'supportlang', 'supportfor']);
foreach ($supportUsers as $userRow) {
	$arr = $userRow->toArray();
	$countryrow = get_country_row($arr['country']);
	$ppl .= "<tr><td class=embedded>". get_username($arr['id']) ."</td><td class=embedded><img width=24 height=15 src=\"pic/flag/".$countryrow['flagpic']."\" title=\"".$countryrow['name']."\" style=\"padding-bottom:1px;\"></td>
 <td class=embedded> ".(strtotime($arr['last_access']) > $dt ? $onlineimg : $offlineimg)."</td>".
 "<td class=embedded><a href=sendmessage.php?receiver=".$arr['id']." title=\"".$lang_staff['title_send_pm']."\">".$sendpmimg."</a></td>".
 "<td class=embedded>".$arr['supportlang']."</td>".
 "<td class=embedded>".$arr['supportfor']."</td></tr>\n";
}

begin_frame($lang_staff['text_firstline_support']."<font class=small> - [<a class=altlink href=contactstaff.php><b>".$lang_staff['text_apply_for_it']."</b></a>]</font>");
?>
<?php echo $lang_staff['text_firstline_support_note'] ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
	<tr>
		<td class=embedded><b><?php echo $lang_staff['text_username'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_language'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_support_for'] ?></b></td>
	</tr>
	<tr>
		<td class=embedded colspan=6>
			<hr color="#4040c0">
		</td>
	</tr>
	<?php echo $ppl?>
</table>
<?php
end_frame();

//--------------------- FIRST LINE SUPPORT SECTION ---------------------------//

//--------------------- film critics section ---------------------------//
$ppl = '';
$pickerUsers = \App\Models\User::query()->where('picker', 'yes')->where('status', 'confirmed')->orderBy('username')->get(['id', 'country', 'last_access', 'pickfor']);
foreach ($pickerUsers as $userRow) {
	$arr = $userRow->toArray();
	$countryrow = get_country_row($arr['country']);
	$ppl .= "<tr height=15><td class=embedded>". get_username($arr['id']) ."</td><td class=embedded ><img width=24 height=15 src=\"pic/flag/".$countryrow['flagpic']."\" title=\"".$countryrow['name']."\" style=\"padding-bottom:1px;\"></td>
 <td class=embedded> ".(strtotime($arr['last_access']) > $dt ? $onlineimg : $offlineimg)."</td>".
 "<td class=embedded><a href=sendmessage.php?receiver=".$arr['id']." title=\"".$lang_staff['title_send_pm']."\">".$sendpmimg."</a></td>".
 "<td class=embedded>".$arr['pickfor']."</td></tr>\n";
}

begin_frame($lang_staff['text_movie_critics']."<font class=small> - [<a class=altlink href=contactstaff.php><b>".$lang_staff['text_apply_for_it']."</b></a>]</font>");
?>
<?php echo $lang_staff['text_movie_critics_note'] ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
	<tr>
		<td class=embedded><b><?php echo $lang_staff['text_username'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_responsible_for'] ?></b></td>
	</tr>
	<tr>
		<td class=embedded colspan=5>
			<hr color="#4040c0">
		</td>
	</tr>
	<?php echo $ppl?>
</table>
<?php
end_frame();

//--------------------- film critics section ---------------------------//

//--------------------- forum moderators section ---------------------------//
$ppl = '';
$forumMods = \Nexus\Database\NexusDB::table('forummods')
    ->leftJoin('users', 'forummods.userid', '=', 'users.id')
    ->orderBy('forummods.forumid')
    ->orderBy('forummods.userid')
    ->get(['forummods.userid AS userid', 'users.last_access', 'users.country'])
    ->unique('userid')
    ->values();
foreach ($forumMods as $modRow) {
	$arr = (array) $modRow;
	$countryrow = get_country_row($arr['country']);
	$forums = "";
	$forumRows = \Nexus\Database\NexusDB::table('forums as f')
	    ->leftJoin('forummods as fm', 'f.id', '=', 'fm.forumid')
	    ->where('fm.userid', $arr['userid'])
	    ->get(['f.id', 'f.name']);
	foreach ($forumRows as $forumRow) {
	$forumrow = (array) $forumRow;
		$forums .= "<a href=forums.php?action=viewforum&forumid=".$forumRow['id'].">".$forumRow['name']."</a>, ";
	}
	$forums = rtrim(trim($forums),",");
	$ppl .= "<tr height=15><td class=embedded>". get_username($arr['userid']) ."</td><td class=embedded ><img width=24 height=15 src=\"pic/flag/".$countryrow['flagpic']."\" title=\"".$countryrow['name']."\" style=\"padding-bottom:1px;\"></td>
 <td class=embedded> ".(strtotime($arr['last_access']) > $dt ? $onlineimg : $offlineimg)."</td>".
 "<td class=embedded><a href=sendmessage.php?receiver=".$arr['userid']." title=\"".$lang_staff['title_send_pm']."\">".$sendpmimg."</a></td>".
 "<td class=embedded>".$forums."</td></tr>\n";
}

begin_frame($lang_staff['text_forum_moderators']."<font class=small> - [<a class=altlink href=contactstaff.php><b>".$lang_staff['text_apply_for_it']."</b></a>]</font>");
?>
<?php echo $lang_staff['text_forum_moderators_note'] ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
	<tr>
		<td class=embedded><b><?php echo $lang_staff['text_username'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?></b></td>
		<td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_forums'] ?></b></td>
	</tr>
	<tr>
		<td class=embedded colspan=5>
			<hr color="#4040c0">
		</td>
	</tr>
	<?php echo $ppl?>
</table>
<?php
end_frame();

//--------------------- film critics section ---------------------------//

//--------------------- general staff section ---------------------------//
$ppl = '';
$staffUsers = \App\Models\User::query()->where('class', '>', UC_VIP)->where('status', 'confirmed')->orderByDesc('class')->orderBy('username')->get();
$curr_class = '';
foreach ($staffUsers as $userRow) {
	$arr = $userRow->toArray();
	if($curr_class != $arr['class'])
	{
		$curr_class = $arr['class'];
		if ($ppl != "")
			$ppl .= "<tr height=15><td class=embedded colspan=5 align=right>&nbsp;</td></tr>";
		$ppl .= "<tr height=15><td class=embedded colspan=5 align=right>" . get_user_class_name($arr["class"],false,true,true) . "</td></tr>";
		$ppl .= "<tr>" .
		"<td class=embedded><b>" . $lang_staff['text_username'] . "</b></td>".
		"<td class=embedded align=center><b>" . $lang_staff['text_country'] . "</b></td>".
		"<td class=embedded align=center><b>" . $lang_staff['text_online_or_offline'] . "</b></td>".
		"<td class=embedded align=center><b>" . $lang_staff['text_contact'] . "</b></td>".
		"<td class=embedded><b>" . $lang_staff['text_duties'] . "</b></td>".
		"</tr>";
		$ppl .= "<tr height=15><td class=embedded colspan=5><hr color=\"#4040c0\"></td></tr>";
	}
	$countryrow = get_country_row($arr['country']);
	$ppl .= "<tr><td class=embedded>". get_username($arr['id']) ."</td><td class=embedded ><img width=24 height=15 src=\"pic/flag/".$countryrow['flagpic']."\" title=\"".$countryrow['name']."\" style=\"padding-bottom:1px;\"></td>
 <td class=embedded> ".(strtotime($arr['last_access']) > $dt ? $onlineimg : $offlineimg)."</td>".
 "<td class=embedded><a href=sendmessage.php?receiver=".$arr['id']." title=\"".$lang_staff['title_send_pm']."\">".$sendpmimg."</a></td>".
 "<td class=embedded>".$arr['stafffor']."</td></tr>\n";
}

begin_frame($lang_staff['text_general_staff']."<font class=small> - [<a class=altlink href=contactstaff.php><b>".$lang_staff['text_apply_for_it']."</b></a>]</font>");
?>
<?php echo $lang_staff['text_general_staff_note'] ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
	<?php echo $ppl?>
</table>
<?php
end_frame();

//--------------------- general staff section ---------------------------//


//--------------------- VIP section ---------------------------//

$ppl = '';
$vipUsers = \App\Models\User::query()->where('class', UC_VIP)->where('status', 'confirmed')->orderBy('username')->get();
foreach ($vipUsers as $userRow) {
	$arr = $userRow->toArray();
	$countryrow = get_country_row($arr['country']);
	$ppl .= "<tr><td class=embedded>". get_username($arr['id']) ."</td><td class=embedded><img width=24 height=15 src=\"pic/flag/".$countryrow['flagpic']."\" title=\"".$countryrow['name']."\" style=\"padding-bottom:1px;\"></td>
 <td class=embedded> ".(strtotime($arr['last_access']) > $dt ? $onlineimg : $offlineimg)."</td>".
 "<td class=embedded><a href=sendmessage.php?receiver=".$arr['id']." title=\"".$lang_staff['title_send_pm']."\">".$sendpmimg."</a></td>".
 "<td class=embedded>".$arr['stafffor']."</td></tr>\n";
}

begin_frame($lang_staff['text_vip']);
?>
<?php echo sprintf($lang_staff['text_vip_note'], \App\Models\Setting::getSiteName()) ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
	<tr>
		<td class=embedded><b><?php echo $lang_staff['text_username'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_country'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_online_or_offline'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_contact'] ?></b></td>
		<td class=embedded><b><?php echo $lang_staff['text_reason'] ?></b></td>
	</tr>
	<tr>
		<td class=embedded colspan=5>
			<hr color="#4040c0">
		</td>
	</tr>
	<?php echo $ppl?>
</table>
<?php
end_frame();

//--------------------- VIP section ---------------------------//
end_main_frame();
	$Cache->end_whole_row();
	$Cache->cache_page();
}
echo $Cache->next_row();
stdfoot();
