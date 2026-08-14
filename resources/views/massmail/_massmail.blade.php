<?php
?>
<p><table border=0 class=main cellspacing=0 cellpadding=0><tr>
<td class=embedded style='padding-left: 10px'><font size=3><b>Send mass e-mail to all members</b></font></td>
</tr></table></p>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=massmail.php>

<?php if ($currentUserIsModerator && $currentClass > UC_POWER_USER): ?>
<input type=hidden name=class value="<?php echo (int) $currentClass; ?>">
<?php else: ?>
<tr><td class=rowhead>Classe</td><td colspan=2 align=left>
<select name=or>
<option value='<'><</option>
<option value='>'>></option>
<option value='='>=</option>
<option value='<='><=</option>
<option value='>='>>=</option>
</select>
<select name=class>
<?php foreach ($classOptions as $opt): ?>
<option value="<?php echo (int) $opt['value']; ?>"<?php echo $opt['selected'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
<?php endforeach; ?>
</select></td></tr>
<?php endif; ?>

<tr><td class=rowhead>Subject</td><td><input type=text name=subject size=80></td></tr>
<tr><td class=rowhead>Body</td><td><textarea name=message cols=80 rows=20></textarea></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send" class=btn></td></tr>
</form>
</table>

<?php
?>
