<?php
?>
<h1 style="text-align: center"><?php echo $title; ?></h1>

<div>
    <form id="filterForm" action="" method="get">
        <input id="q" type="text" name="q" value="<?php echo htmlspecialchars((string) $q); ?>" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>

<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
    <td class="colhead">ID</td>
    <td class="colhead"><?php echo $columnImageLargeLabel; ?></td>
    <td class="colhead"><?php echo $columnDescriptionLabel; ?></td>
    <td class="colhead" style="width: 115px"><?php echo $columnSaleBeginEndTimeLabel; ?></td>
    <td class="colhead"><?php echo $columnDurationLabel; ?></td>
    <td class="colhead"><?php echo $columnBonusAdditionLabel; ?></td>
    <td class="colhead"><?php echo $columnPriceLabel; ?></td>
    <td class="colhead"><?php echo $columnInventoryLabel; ?></td>
    <td class="colhead"><?php echo $columnBuyLabel; ?></td>
    <td class="colhead"><?php echo $columnGiftLabel; ?></td>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
    <td><?php echo (int) $row['id']; ?></td>
    <td><img src="<?php echo $row['image_large']; ?>" style="max-width: 60px;max-height: 60px;" class="preview" /></td>
    <td><h1><?php echo htmlspecialchars((string) $row['name']); ?></h1><?php echo $row['description']; ?></td>
    <td><?php echo $row['sale_begin_time']; ?> ~<br><?php echo $row['sale_end_time']; ?></td>
    <td><?php echo $row['durationText']; ?></td>
    <td><?php echo $row['bonus_addition_factor']; ?>%</td>
    <td><?php echo number_format((float) $row['price']); ?></td>
    <td><?php echo $row['inventory']; ?></td>
    <td><?php echo $row['buy_action']; ?></td>
    <td><?php echo $row['gift_action']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php echo $pagerbottom; ?>

<?php
?>
