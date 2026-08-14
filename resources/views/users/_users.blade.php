<?php
?>
<?php echo $lang_users['text_users'] ?? 'Users'; ?>

<form method=get action=?>
<?php echo $lang_users['text_search'] ?? 'Search:'; ?> <input type=text style="width:100px" name=search value="<?php echo htmlspecialchars((string) $search); ?>">
<select name=class>
<option value='-'><?php echo $lang_users['select_any_class'] ?? 'Any class'; ?></option>
<?php foreach ($classOptions as $opt): ?>
<option value="<?php echo (int) $opt['value']; ?>"<?php echo $opt['selected'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
<?php endforeach; ?>
</select>
<select name=country>
<?php foreach ($countryOptions as $opt): ?>
<option value="<?php echo (int) $opt['value']; ?>"<?php echo $opt['selected'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $opt['label']); ?></option>
<?php endforeach; ?>
</select>
<input type=submit value="<?php echo $lang_users['submit_okay'] ?? 'OK'; ?>">
</form>

<p>
<?php for ($i = 97; $i < 123; ++$i): ?>
<?php
$l = chr($i);
$L = chr($i - 32);
if ($l == $letter) {
    echo "<font class=gray><b>{$L}</b></font>\n";
} else {
    if ($class == '-') {
        $href = "?letter={$l}" . ($country > 0 ? "&country={$country}" : "");
    } else {
        $href = "?letter={$l}&class={$class}" . ($country > 0 ? "&country={$country}" : "");
    }
    echo "<a href=\"{$href}\"><b>{$L}</b></a>\n";
}
?>
<?php endfor; ?>
</p>

<?php echo $pagertop; ?>

<table border=1 cellspacing=0 cellpadding=5>
<tr>
    <td class=colhead align=left><?php echo $lang_users['col_user_name'] ?? 'User name'; ?></td>
    <td class=colhead><?php echo $lang_users['col_registered'] ?? 'Registered'; ?></td>
    <td class=colhead><?php echo $lang_users['col_last_access'] ?? 'Last access'; ?></td>
    <td class=colhead align=left><?php echo $lang_users['col_class'] ?? 'Class'; ?></td>
    <td class=colhead><?php echo $lang_users['col_country'] ?? 'Country'; ?></td>
</tr>
<?php foreach ($rows as $row): ?>
<tr>
    <td align=left><?php echo $row['username_html']; ?></td>
    <td><?php echo \App\Support\Time::format($row['added'], true, false); ?></td>
    <td><?php echo \App\Support\Time::format($row['last_access'], true, false); ?></td>
    <td align=left><?php echo $row['class_name']; ?></td>
    <td align=center><?php echo htmlspecialchars((string) $row['country']); ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php echo $pagerbottom; ?>

<?php
?>
