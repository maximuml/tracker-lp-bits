<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$id = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
\App\Support\LegacyResponse::assertId($id, true);


$userObj = \App\Models\User::query()->where('status', 'pending')->where('id', $id)->first();
if (!$userObj) bark($lang_checkuser['std_no_user_id']);
$user = $userObj->toArray();

if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
	if ($user['invited_by'] != $CURUSER['id'])
		bark($lang_checkuser['std_no_permission']);
}

if ($user["gender"] == "Male") $gender = '<img class="male" src="pic/trans.gif" alt="Male" title="Male" style="margin-left: 4pt">';
elseif ($user["gender"] == "Female") $gender = '<img class="female" src="pic/trans.gif" alt="Female" title="Female" style="margin-left: 4pt">';
elseif ($user["gender"] == "N/A") $gender = '<img class="no_gender" src="pic/trans.gif" alt="N/A" title="No gender" style="margin-left: 4pt">';

if ($user['added'] == "0000-00-00 00:00:00" || $user['added'] == null)
  $joindate = 'N/A';
else
  $joindate = "$user[added] (" . \App\Support\Format::getElapsedTime(strtotime($user["added"])) . " ago)";

$country = '';
$countryRow = \Nexus\Database\NexusDB::table('countries')->where('id', $user['country'])->first(['name', 'flagpic']);
if ($countryRow) {
  $arr = (array) $countryRow;
  $country = "<td class=embedded><img src=pic/flag/{$arr['flagpic']} alt=\"{$arr['name']}\" style='margin-left: 8pt'></td>";
}

\App\Support\Html::stdhead($lang_checkuser['head_detail_for'] . $user["username"]);

$enabled = $user["enabled"] == 'yes';
print("<p><table class=main border=0 cellspacing=0 cellpadding=0>".
"<tr><td class=embedded><h1 style='margin:0px'>" . \App\Support\UserDisplay::username($user['id'], true, false) . "</h1></td>$country</tr></table></p><br />\n");

if (!$enabled)
  print($lang_checkuser['text_account_disabled']);
?>
<table width=737 border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_join_date'] ?></td><td align=left width=99%><?php echo $joindate;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_gender'] ?></td><td align=left width=99%><?php echo $gender;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_email'] ?></td><td align=left width=99%><a href=mailto:<?php echo $user['email'];?>><?php echo $user['email'];?></a></td></tr>
<?php
if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR AND $user['ip'] != '')
	print ("<tr><td class=rowhead width=1%>".$lang_checkuser['row_ip']."</td><td align=left width=99%>{$user['ip']}</td></tr>");
print("<form method=post action=takeconfirm.php?id=".htmlspecialchars($id).">");
print("<input type=hidden name=email value={$user['email']}>");
print("<tr><td class=rowhead width=1%><input type=\"checkbox\" name=\"conusr[]\" value=\"" . $id . "\" checked/></td>");
print("<td align=left width=99%><input type=submit style='height: 20px' value=\"".$lang_checkuser['submit_confirm_this_user'] ."\"></form></tr></td></table>");
\App\Support\Html::stdfoot();
