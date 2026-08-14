<?php
$mode = $mode ?? 'list';
$forums = $forums ?? [];
$overforums = $overforums ?? [];
$classOptions = $classOptions ?? [];
$maxSort = $maxSort ?? 0;
$id = $id ?? 0;
$row = $row ?? [];
$moderatorUsernames = $moderatorUsernames ?? '';
$currentClass = $currentClass ?? 0;

if ($mode === 'editforum'):
?>
<h1 align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management'] ?? 'Forum management'; ?></a><b>--></b><?php echo $lang_forummanage['text_edit_forum'] ?? 'Edit forum'; ?></h1>
<br />
<?php if (empty($row)): ?>
    <p><?php echo $lang_forummanage['text_no_records_found'] ?? 'No records found.'; ?></p>
<?php else: ?>
<form method=post action="forummanage.php">
@csrf
<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center"><td colspan="2" class=colhead><?php echo ($lang_forummanage['text_edit_forum'] ?? 'Edit forum') . ' -- ' . htmlspecialchars((string) $row['name']); ?></td></tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_name'] ?? 'Name'; ?></b></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo htmlspecialchars((string) $row['name']); ?>"></td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_description'] ?? 'Description'; ?></b></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo htmlspecialchars((string) $row['description']); ?>"></td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_overforum'] ?? 'Overforum'; ?></b></td>
    <td>
    <select name=overforums>
    <?php foreach ($overforums as $of): ?>
    <option value="<?php echo (int) $of['id']; ?>"<?php echo $row['forid'] == $of['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $of['name']); ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr><td><b><?php echo $lang_forummanage['row_moderator'] ?? 'Moderator'; ?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200" value="<?php echo htmlspecialchars((string) $moderatorUsernames); ?>">&nbsp;<?php echo $lang_forummanage['text_moderator_note'] ?? ''; ?></td></tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission'] ?? 'Read'; ?></b></td>
    <td>
    <select name=readclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $row['minclassread'] == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission'] ?? 'Write'; ?></b></td>
    <td>
    <select name=writeclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $row['minclasswrite'] == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission'] ?? 'Create topic'; ?></b></td>
    <td>
    <select name=createclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $row['minclasscreate'] == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_order'] ?? 'Order'; ?></b></td>
    <td>
    <select name=sort>
    <?php for ($i = 0; $i <= $maxSort + 1; ++$i): ?>
    <option value="<?php echo $i; ?>"<?php echo $row['sort'] == $i ? ' selected' : ''; ?>><?php echo $i; ?></option>
    <?php endfor; ?>
    </select>
    <?php echo $lang_forummanage['text_forum_order_note'] ?? ''; ?>
    </td>
</tr>
<tr align="center"><td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_edit_forum'] ?? 'Edit'; ?>" class="btn"></td></tr>
</table>
</form>
<?php endif; ?>

<?php elseif ($mode === 'newforum'): ?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management'] ?? 'Forum management'; ?></a><b>--></b><?php echo $lang_forummanage['text_add_forum'] ?? 'Add forum'; ?></h2>
<br />
<form method=post action="forummanage.php">
@csrf
<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center"><td colspan="2" class=colhead><?php echo $lang_forummanage['text_make_new_forum'] ?? 'Make new forum'; ?></td></tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_name'] ?? 'Name'; ?></b></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_description'] ?? 'Description'; ?></b></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_overforum'] ?? 'Overforum'; ?></b></td>
    <td>
    <select name=overforums>
    <?php foreach ($overforums as $of): ?>
    <option value="<?php echo (int) $of['id']; ?>"><?php echo htmlspecialchars((string) $of['name']); ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr><td><b><?php echo $lang_forummanage['row_moderator'] ?? 'Moderator'; ?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200">&nbsp;<?php echo $lang_forummanage['text_moderator_note'] ?? ''; ?></td></tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission'] ?? 'Read'; ?></b></td>
    <td>
    <select name=readclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $currentClass == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission'] ?? 'Write'; ?></b></td>
    <td>
    <select name=writeclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $currentClass == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission'] ?? 'Create topic'; ?></b></td>
    <td>
    <select name=createclass>
    <?php foreach ($classOptions as $opt): ?>
    <option value="<?php echo (int) $opt['value']; ?>"<?php echo $currentClass == $opt['value'] ? ' selected' : ''; ?>><?php echo $opt['label']; ?></option>
    <?php endforeach; ?>
    </select>
    </td>
</tr>
<tr>
    <td><b><?php echo $lang_forummanage['row_forum_order'] ?? 'Order'; ?></b></td>
    <td>
    <select name=sort>
    <?php for ($i = 0; $i <= $maxSort + 1; ++$i): ?>
    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
    <?php endfor; ?>
    </select>
    <?php echo $lang_forummanage['text_forum_order_note'] ?? ''; ?>
    </td>
</tr>
<tr align="center"><td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_make_forum'] ?? 'Add'; ?>" class="btn"></td></tr>
</table>
</form>

<?php else: ?>
<h2 class=transparentbg align=center><?php echo $lang_forummanage['text_forum_management'] ?? 'Forum management'; ?></h2>
<table border=0 class=main cellspacing=0 cellpadding=5 width=1%><tr>
<td class=embedded align=left><form method="get" action="moforums.php"><input type="submit" value="<?php echo $lang_forummanage['submit_overforum_management'] ?? 'Overforum management'; ?>" class="btn"></form></td><td class=embedded align=left><form method="get" action="forummanage.php"><input type=hidden name="action" value="newforum"><input type="submit" value="<?php echo $lang_forummanage['submit_add_forum'] ?? 'Add forum'; ?>" class="btn"></form></td>
</tr></table>
<?php
    echo '<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">';
    echo '<tr><td class=colhead align=left>' . ($lang_forummanage['col_name'] ?? 'Name') . '</td><td class=colhead>' . ($lang_forummanage['col_overforum'] ?? 'Overforum') . '</td><td class=colhead>' . ($lang_forummanage['col_read'] ?? 'Read') . '</td><td class=colhead>' . ($lang_forummanage['col_write'] ?? 'Write') . '</td><td class=colhead>' . ($lang_forummanage['col_create_topic'] ?? 'Create') . '</td><td class=colhead>' . ($lang_forummanage['col_moderator'] ?? 'Moderator') . '</td><td class=colhead>' . ($lang_forummanage['col_modify'] ?? 'Modify') . '</td></tr>';
    if (empty($forums)) {
        echo '<tr><td colspan=6>' . ($lang_forummanage['text_no_records_found'] ?? 'No records found.') . '</td></tr>';
    } else {
        foreach ($forums as $row) {
            $moderators = $row['moderators_html'] ?: ($lang_forummanage['text_not_available'] ?? 'N/A');
            echo '<tr><td><a href=forums.php?action=viewforum&forumid=' . (int) $row['id'] . '><b>' . htmlspecialchars((string) $row['name']) . '</b></a><br />' . htmlspecialchars((string) $row['description']) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['of_name'] ?? '')) . '</td><td>' . \App\Support\UserClass::name((int) $row['minclassread'], false, true, true) . '</td><td>' . \App\Support\UserClass::name((int) $row['minclasswrite'], false, true, true) . '</td><td>' . \App\Support\UserClass::name((int) $row['minclasscreate'], false, true, true) . '</td><td>' . $moderators . '</td><td><b><a href="forummanage.php?action=editforum&id=' . (int) $row['id'] . '">' . ($lang_forummanage['text_edit'] ?? 'Edit') . '</a>&nbsp;|&nbsp;<a href="javascript:confirm_delete(\'' . (int) $row['id'] . '\', \'' . ($lang_forummanage['js_sure_to_delete_forum'] ?? '') . '\', \'\');"><font color=red>' . ($lang_forummanage['text_delete'] ?? 'Delete') . '</font></a></b></td></tr>';
        }
    }
    echo '</table>';
endif;
?>
