<?php
\App\Support\Html::stdhead($lang_uploaders['head_uploaders'] ?? 'Uploaders');
\App\Support\Frame::mainFrameOpen();
?>
<div style="width: 940px">
<h1 align="center"><?php echo $lang_uploaders['text_uploaders'] ?? 'Uploaders'; ?> - <?php echo date('Y-m', $timeStart); ?></h1>

<div>
<form method="get" action="?">
<span>
<?php echo $lang_uploaders['text_select_month'] ?? 'Select month:'; ?>
<select name="year"><?php echo $yearOptions; ?></select>
&nbsp;&nbsp;
<select name="month"><?php echo $monthOptions; ?></select>
&nbsp;&nbsp;
<input type="submit" value="<?php echo $lang_uploaders['submit_go'] ?? 'Go'; ?>" />
</span>
</form>
</div>

<?php if (empty($rows)): ?>
<p align="center"><?php echo $lang_uploaders['text_no_uploaders_yet'] ?? 'No uploaders yet.'; ?></p>
<?php else: ?>
<div style="margin-top: 8px">
<table border="1" cellspacing="0" cellpadding="5" align="center" width="97%">
<tr>
    <td class="colhead"><?php echo $lang_uploaders['col_username'] ?? 'Username'; ?></td>
    <td class="colhead"><?php echo $lang_uploaders['col_torrents_size'] ?? 'Torrents size'; ?></td>
    <td class="colhead"><?php echo $lang_uploaders['col_torrents_num'] ?? 'Torrents num'; ?></td>
    <td class="colhead"><?php echo $lang_uploaders['col_last_upload_time'] ?? 'Last upload time'; ?></td>
    <td class="colhead"><?php echo $lang_uploaders['col_last_upload'] ?? 'Last upload'; ?></td>
</tr>
<?php foreach ($rows as $row): ?>
<tr>
    <td class="colfollow"><?php echo \App\Support\UserDisplay::username($row['userid'], false, true, true, false, false, true); ?></td>
    <td class="colfollow"><?php echo $row['torrent_size'] ? \App\Support\Format::size($row['torrent_size']) : '0'; ?></td>
    <td class="colfollow"><?php echo (int) $row['torrent_count']; ?></td>
    <td class="colfollow"><?php echo $row['last_added'] ? \App\Support\Time::format($row['last_added']) : ($lang_uploaders['text_not_available'] ?? 'N/A'); ?></td>
    <td class="colfollow"><?php echo $row['last_name'] ? '<a href="details.php?id=' . (int) $row['last_id'] . '">' . htmlspecialchars((string) $row['last_name']) . '</a>' : ($lang_uploaders['text_not_available'] ?? 'N/A'); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<div style="margin-top: 8px; margin-bottom: 8px;">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_uploaders['text_order_by'] ?? 'Order by'; ?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?year=<?php echo (int) $year; ?>&amp;month=<?php echo (int) $month; ?>&amp;order=username"><?php echo $lang_uploaders['text_username'] ?? 'Username'; ?></a></li>
<li><a href="?year=<?php echo (int) $year; ?>&amp;month=<?php echo (int) $month; ?>&amp;order=torrent_size"><?php echo $lang_uploaders['text_torrent_size'] ?? 'Torrent size'; ?></a></li>
<li><a href="?year=<?php echo (int) $year; ?>&amp;month=<?php echo (int) $month; ?>&amp;order=torrent_count"><?php echo $lang_uploaders['text_torrent_num'] ?? 'Torrent num'; ?></a></li>
</ul>
</span>
</span>
</div>
<?php endif; ?>
</div>
<?php
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
