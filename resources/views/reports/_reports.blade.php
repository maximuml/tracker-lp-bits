<?php
$lang_reports = (array) (\app(\App\Support\Globals::class)->get('lang_reports') ?? []);
print('<h1 align="center">' . ($lang_reports['text_reports'] ?? 'Reports') . '</h1>');
print("<table border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<form method=post action=takeupdate.php>");
?>
<tr>
    <td class="colhead"><nobr><?php echo $lang_reports['col_added'] ?? 'Added'; ?></nobr></td>
    <td class="colhead"><?php echo $lang_reports['col_reporter'] ?? 'Reporter'; ?></td>
    <td class="colhead"><?php echo $lang_reports['col_reporting'] ?? 'Reporting'; ?></td>
    <td class="colhead"><nobr><?php echo $lang_reports['col_type'] ?? 'Type'; ?></nobr></td>
    <td class="colhead"><?php echo $lang_reports['col_reason'] ?? 'Reason'; ?></td>
    <td class="colhead"><nobr><?php echo $lang_reports['col_dealt_with'] ?? 'Dealt with'; ?></nobr></td>
    <td class="colhead"><nobr><?php echo $lang_reports['col_action'] ?? 'Action'; ?></nobr></td>
</tr>
<?php foreach ($rows as $row): ?>
    <tr>
        <td class="rowfollow"><nobr><?php echo $row['added_formatted']; ?></nobr></td>
        <td class="rowfollow"><?php echo \App\Support\UserDisplay::username($row['addedby']); ?></td>
        <td class="rowfollow"><?php echo $row['reporting']; ?></td>
        <td class="rowfollow"><nobr><?php echo $row['type_label']; ?></nobr></td>
        <td class="rowfollow"><?php echo htmlspecialchars((string) $row['reason']); ?></td>
        <td class="rowfollow"><nobr><?php echo $row['dealtwith_html']; ?></nobr></td>
        <td class="rowfollow"><input type="checkbox" name="delreport[]" value="<?php echo (int) $row['id']; ?>" /></td>
    </tr>
<?php endforeach; ?>
<tr>
    <td class="colhead" colspan="7" align="right">
        <input type="submit" name="setdealt" value="<?php echo $lang_reports['submit_set_dealt'] ?? 'Set dealt'; ?>" />
        <input type="submit" name="delete" value="<?php echo $lang_reports['submit_delete'] ?? 'Delete'; ?>" />
    </td>
</tr>
</form>
<?php
print("</table>");
print($pagerbottom);
?>
