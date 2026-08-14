<?php
?>
<h1 align=center><?php echo $title; ?><a href="userdetails.php?id=<?php echo (int) $uid; ?>"><b>&nbsp;<?php echo htmlspecialchars((string) $username); ?></b></a></h1>

<div>
    <form id="filterForm" action="<?php echo htmlspecialchars((string) ($__server_REQUEST_URI ?? $_SERVER['REQUEST_URI'] ?? '')); ?>" method="get">
        <input type="hidden" name="uid" value="<?php echo (int) $uid; ?>" />
        <span><?php echo $categoryText; ?>:</span>
        <select name="category">
            <?php echo $categoryOptionsHtml; ?>
        </select>
        &nbsp;&nbsp;
        <span><?php echo $businessTypeText; ?>:</span>
        <select name="business_type">
            <option value="0">-<?php echo $textSelectOnePlease; ?>-</option>
            <?php echo $businessTypeOptionsHtml; ?>
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="<?php echo $submitText; ?>">
        <input type="button" id="reset" value="<?php echo $resetText; ?>">
    </form>
</div>

<table id='bonus-log-table' width='100%' cellpadding='5'>
<tr>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('bonus-log.fields.business_type', [], null); ?></td>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('bonus-log.fields.old_total_value', [], null); ?></td>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('bonus-log.fields.value', [], null); ?></td>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('bonus-log.fields.new_total_value', [], null); ?></td>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('label.comment', [], null); ?></td>
    <td class='colhead' align='left'><?php echo \App\Support\Locale::trans('label.created_at', [], null); ?></td>
</tr>
<?php foreach ($rows as $row): ?>
<tr>
    <td class='rowfollow nowrap' align='left'><?php echo $row['businessTypeText']; ?></td>
    <td class='rowfollow nowrap' align='left'><?php echo $row['old_formatted']; ?></td>
    <td class='rowfollow nowrap' align='left'><?php echo $row['value_formatted']; ?></td>
    <td class='rowfollow nowrap' align='left'><?php echo $row['new_formatted']; ?></td>
    <td class='rowfollow nowrap' align='left'><?php echo $row['comment']; ?></td>
    <td class='rowfollow nowrap' align='left'><?php echo $row['created_at']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php echo $pagerbottom; ?>
