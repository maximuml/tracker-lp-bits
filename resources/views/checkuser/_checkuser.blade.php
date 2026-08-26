<?php
$lang_checkuser = (array) ($lang_checkuser ?? \app(\App\Support\Globals::class)->get('lang_checkuser', []));


print('<p><table class=main border=0 cellspacing=0 cellpadding=0>'.
'<tr><td class=embedded><h1 style=\'margin:0px\'>' . \App\Support\UserDisplay::username($user['id'], true, false) . '</h1></td>'.$country.'</tr></table></p><br />\n');

if (! $enabled)
  print($lang_checkuser['text_account_disabled'] ?? '');
?>
<table width=737 border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_join_date'] ?></td><td align=left width=99%><?php echo $joindate;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_gender'] ?></td><td align=left width=99%><?php echo $gender;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_email'] ?></td><td align=left width=99%><a href=mailto:<?php echo $user['email'];?>><?php echo $user['email'];?></a></td></tr>
<?php if ($canSeeIp): ?>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_ip'] ?></td><td align=left width=99%><?php echo $user['ip'] ?></td></tr>
<?php endif; ?>
<form method=post action=takeconfirm.php?id=<?php echo htmlspecialchars((string) $id); ?>>
<input type=hidden name=email value=<?php echo $user['email']; ?>>
<tr><td class=rowhead width=1%><input type="checkbox" name="conusr[]" value="<?php echo $id; ?>" checked/></td>
<td align=left width=99%><input type=submit style='height: 20px' value="<?php echo $lang_checkuser['submit_confirm_this_user']; ?>"></form></tr></td></table>
<?php
?>
