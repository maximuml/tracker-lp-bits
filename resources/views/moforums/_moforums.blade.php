<?php
\App\Support\Html::stdhead($lang_moforums['head_overforum_management'] ?? 'Overforum management');
\App\Support\Frame::mainFrameOpen();

if ($mode === 'editforum'):
?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management'] ?? 'Forum management'; ?></a><b>--></b><a class=faqlink href=moforums.php><?php echo $lang_moforums['text_overforum_management'] ?? 'Overforum management'; ?></a><b>--></b><?php echo $lang_moforums['text_edit_overforum'] ?? 'Edit overforum'; ?></h2><br />
<form method=post action="moforums.php">
@csrf
<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center"><td colspan="2" class=colhead><?php echo $lang_moforums['text_edit_overforum'] ?? 'Edit overforum'; ?> -- <?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td></tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_name'] ?? 'Name'; ?></b></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?>"></td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_description'] ?? 'Description'; ?></b></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo htmlspecialchars((string) ($row['description'] ?? '')); ?>"></td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission'] ?? 'Minimum view permission'; ?></b></td>
    <td>
    <select name=viewclass>
    <?php foreach ($viewclassOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo ($row['minclassview'] ?? 0) == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_order'] ?? 'Order'; ?></b></td>
    <td>
    <select name=sort>
    <?php foreach ($sortOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo ($row['sort'] ?? 0) == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    <?php echo $lang_moforums['text_overforum_order_note'] ?? ''; ?>
    </td>
</tr>
<tr align="center"><td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_edit_overforum'] ?? 'Edit'; ?>"></td></tr>
</table>
</form>
<?php
else:
?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management'] ?? 'Forum management'; ?></a><b>--></b><?php echo $lang_moforums['text_overforum_management'] ?? 'Overforum management'; ?></h2>
<br />
<?php
    echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
    echo "<tr><td class=colhead align=left>" . ($lang_moforums['col_name'] ?? 'Name') . "</td><td class=colhead>" . ($lang_moforums['col_viewed_by'] ?? 'Viewed by') . "</td><td class=colhead>" . ($lang_moforums['col_modify'] ?? 'Modify') . "</td></tr>";
    if (empty($overforums)) {
        echo '<tr><td colspan=3>' . ($lang_moforums['text_no_records_found'] ?? 'No records found.') . '</td></tr>';
    } else {
        foreach ($overforums as $forumRow) {
            $row = (array) $forumRow;
            echo '<tr><td><a href=forums.php?action=forumview&forid=' . (int) $row['id'] . '><b>' . htmlspecialchars((string) $row['name']) . '</b></a><br />' . ($row['description'] ?? '') . '</td>';
            echo '<td>' . UserClass::name((int) $row['minclassview'], false, true, true) . '</td><td><b><a href="moforums.php?action=editforum&id=' . (int) $row['id'] . '">' . ($lang_moforums['text_edit'] ?? 'Edit') . '</a>&nbsp;|&nbsp;<a href="javascript:confirm_delete(\'' . (int) $row['id'] . '\', \'' . ($lang_moforums['js_sure_to_delete_overforum'] ?? '') . '\', \'\');"><font color=red>' . ($lang_moforums['text_delete'] ?? 'Delete') . '</font></a></b></td></tr>';
        }
    }
    echo '</table>';
?>
<br /><br />
<form method=post action="moforums.php">
@csrf
<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center"><td colspan="2" class=colhead><?php echo $lang_moforums['text_new_overforum'] ?? 'New overforum'; ?></td></tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_name'] ?? 'Name'; ?></b></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_description'] ?? 'Description'; ?></b></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission'] ?? 'Minimum view permission'; ?></b></td>
    <td>
    <select name=viewclass>
    <?php foreach ($viewclassOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $currentClass == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_moforums['text_overforum_order'] ?? 'Order'; ?></b></td>
    <td>
    <select name=sort>
    <?php foreach ($sortOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    <?php echo $lang_moforums['text_overforum_order_note'] ?? ''; ?>
    </td>
</tr>
<tr align="center"><td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_make_overforum'] ?? 'Make overforum'; ?>"></td></tr>
</table>
</form>
<?php
endif;
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
