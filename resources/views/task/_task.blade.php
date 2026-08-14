<?php
\App\Support\Html::stdhead($title);
\App\Support\Frame::mainFrameOpen();
?>
<h1 style="text-align: center"><?php echo $title; ?></h1>

<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
    <td class="colhead"><?php echo $columnNameLabel; ?></td>
    <td class="colhead"><?php echo $columnIndexLabel; ?></td>
    <td class="colhead"><?php echo $columnBeginTimeLabel; ?></td>
    <td class="colhead"><?php echo $columnEndTimeLabel; ?></td>
    <td class="colhead"><?php echo $columnTargetUserLabel; ?></td>
    <td class="colhead"><?php echo $columnSuccessRewardLabel; ?></td>
    <td class="colhead"><?php echo $columnFailDeductLabel; ?></td>
    <td class="colhead"><?php echo $columnClaimedUserCountLabel; ?></td>
    <td class="colhead"><?php echo $columnDescLabel; ?></td>
    <td class="colhead"><?php echo $columnClaimLabel; ?></td>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<?php
$claimDisabled = $row['claimed'] ? ' disabled' : '';
$claimClass = $row['claimed'] ? '' : 'claim';
$claimBtnText = $row['claimed'] ? $claimedText : $claimBtnText;
$claimAction = sprintf('<input type="button" class="%s" data-id="%s" value="%s"%s>', $claimClass, (int) $row['id'], htmlspecialchars($claimBtnText), $claimDisabled);
$claimedCount = $row['on_going_users_count'] . '/' . ($row['max_user_count'] ?: $infiniteText);
?>
<tr>
    <td class="nowrap"><strong><?php echo htmlspecialchars((string) $row['name']); ?></strong></td>
    <td class="nowrap"><?php echo $row['indexFormatted']; ?></td>
    <td><?php echo $row['beginForUser']; ?></td>
    <td><?php echo $row['endForUser']; ?></td>
    <td><?php echo $row['filterFormatted']; ?></td>
    <td><?php echo number_format((float) $row['success_reward_bonus']); ?></td>
    <td><?php echo number_format((float) $row['fail_deduct_bonus']); ?></td>
    <td><?php echo $claimedCount; ?></td>
    <td><?php echo $row['description']; ?></td>
    <td><?php echo $claimAction; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php echo $pagerbottom; ?>

<?php
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
