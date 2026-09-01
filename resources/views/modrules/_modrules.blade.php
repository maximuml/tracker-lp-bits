<?php

use App\Support\Format;

if ($mode === 'newsect') {
    ?>
<h1 align=center>Add Rules</h1>
<form method="post" action="modrules.php?act=addsect">
    @csrf
    <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td align=left><input style="width: 400px;" type="text" name="title"/></td></tr>
        <tr><td style="vertical-align: top;">Rules:</td><td><textarea cols=90 rows=20 name="text"></textarea></td></tr>
        <tr>
            <td>Language:</td>
            <td align="center">
                <select name=language>
                    <?php foreach ($langs as $row) { ?>
                    <option value="<?php echo (int) $row['id']; ?>"<?php echo ($row['site_lang_folder'] ?? '') == $deflang ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $row['lang_name']); ?></option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr><td colspan="2" align="center"><input type="submit" value="Add" style="width: 60px;"></td></tr>
    </table>
</form>
<?php
} elseif ($mode === 'edit') {
    ?>
<h1 align=center>Edit Rules</h1>
<form method="post" action="modrules.php?act=edited">
    @csrf
    <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td align=left><input style="width: 400px;" type="text" name="title" value="<?php echo htmlspecialchars((string) ($rule['title'] ?? '')); ?>" /></td></tr>
        <tr><td style="vertical-align: top;">Rules:</td><td><textarea cols=90 rows=20 name="text"><?php echo htmlspecialchars((string) ($rule['text'] ?? '')); ?></textarea></td></tr>
        <tr>
            <td>Language:</td>
            <td align="center">
                <select name=language>
                    <?php foreach ($langs as $row) { ?>
                    <option value="<?php echo (int) $row['id']; ?>"<?php echo ($row['id'] ?? 0) == ($rule['lang_id'] ?? 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $row['lang_name']); ?></option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr><td colspan="2" align="center"><input type=hidden value="<?php echo (int) ($rule['id'] ?? 0); ?>" name=id><input type="submit" value="Save" style="width: 60px;"></td></tr>
    </table>
</form>
<?php
} else {
    ?>
<h1 align=center>Rules Management</h1>
<br /><table width=940 border=0 cellspacing=0 cellpadding=5>
<tr><td align=center><a href=modrules.php?act=newsect>Add Section</a></td></tr></table>
<?php foreach ($rows as $arr) { ?>
<br /><table width=940 border=1 cellspacing=0 cellpadding=5>
    <tr><td class=colhead><?php echo htmlspecialchars((string) $arr['title']); ?> - <?php echo htmlspecialchars((string) $arr['lang_name']); ?></td></tr>
    <tr><td align=left><?php echo Format::formatComment($arr['text']); ?></td></tr>
    <tr><td align=left><a href="?act=edit&id=<?php echo (int) $arr['id']; ?>">Edit</a>&nbsp;&nbsp;<form method="post" action="modrules.php?act=del" style="display:inline">@csrf<input type="hidden" name="id" value="<?php echo (int) $arr['id']; ?>"><input type="hidden" name="sure" value="1"><button type="submit" style="background:none;border:none;padding:0;margin:0;color:inherit;cursor:pointer;text-decoration:underline">Delete</button></form></td></tr>
</table>
<?php } ?>
<?php
}
?>
