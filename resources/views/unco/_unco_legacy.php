<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR)
\App\Support\LegacyResponse::abort("Sorry", "Access denied.");
$status = \App\Support\SupportContext::getQuery('status');
	if ($status)
		\App\Support\LegacyResponse::assertId($status, true);
		
$rows = \App\Models\User::query()->where('status', 'pending')->orderBy('username')->get();
if( $rows->isNotEmpty() )
{
	\App\Support\Html::stdhead("Unconfirmed Users");
	\App\Support\Frame::mainFrameOpen();
	\App\Support\Html::beginFrame("");
print'<br><table width=100% border=1 cellspacing=0 cellpadding=5>';
if ($status)
	print '<tr><td class=rowhead colspan=5><font color=red size=1>The User account has been updated!</font></tr></td>';
print'<tr>';
print'<td class=rowhead><center>Name</center></td>';
print'<td class=rowhead><center>eMail</center></td>';
print'<td class=rowhead><center>Added</center></td>';
print'<td class=rowhead><center>Set Status</center></td>';
print'<td class=rowhead><center>Confirm</center></td>';
print'</tr>';
foreach ($rows as $userRow)
{
$row = (array) $userRow;
$id = $row['id'];
print'<tr><form method=post action=modtask.php>';
print'<input type=hidden name=\'action\' value=\'confirmuser\'>';
print("<input type=hidden name='userid' value='$id'>");
print'<a href="userdetails.php?id=' . $row['id'] . '"><td><center>' . $row['username'] . '</center></td></a>';
print'<td align=center>&nbsp;&nbsp;&nbsp;&nbsp;' . $row['email'] . '</td>';
print'<td align=center>&nbsp;&nbsp;&nbsp;&nbsp;' . $row['added'] . '</td>';
print'<td align=center><select name=confirm><option value=pending>pending</option><option value=confirmed>confirmed</option></select></td>';
print'<td align=center><input type=submit value="-Go-" style=\'height: 20px; width: 40px\'>';
print'</form></tr>';
}
print '</table>';
\App\Support\Html::endFrame();
\App\Support\Frame::mainFrameClose();
}else{
	if ($status) {
		\App\Support\LegacyResponse::abort("Updated!", "The user account has been updated.");
	}
	else {
		\App\Support\LegacyResponse::abort("Ups!", "Nothing Found...");
	}
}

\App\Support\Html::stdfoot();
