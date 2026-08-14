<?php
print('<h1 align=center>' . ($lang_staffpanel['Administration'] ?? 'Administration') . '</h1>');

if (! empty($sysopPanels)):
?>
<h1 align=center>..:: <?php echo $lang_staffpanel['For SysOp Only'] ?? 'For SysOp Only'; ?> ::..</h1>
<br /><br />
<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left><?php echo $lang_staffpanel['Option Name'] ?? 'Option Name'; ?></td><td class=colhead align=left><?php echo $lang_staffpanel['Info'] ?? 'Info'; ?></td></tr>
<?php foreach ($sysopPanels as $row): ?>
<tr>
    <td class=rowfollow align=left><strong><a href="<?php echo $row['url']; ?>"><?php echo $lang_staffpanel[$row['name']] ?? $row['name']; ?></a></strong></td>
    <td class=rowfollow align=left><?php echo $lang_staffpanel[$row['info']] ?? $row['info']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<br /><br />
<?php
endif;

if (! empty($adminPanels)):
?>
<h1 align=center>..:: <?php echo $lang_staffpanel['For Administrator Only'] ?? 'For Administrator Only'; ?> ::..</h1>
<br /><br />
<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left><?php echo $lang_staffpanel['Option Name'] ?? 'Option Name'; ?></td><td class=colhead align=left><?php echo $lang_staffpanel['Info'] ?? 'Info'; ?></td></tr>
<?php foreach ($adminPanels as $row): ?>
<tr>
    <td class=rowfollow align=left><strong><a href="<?php echo $row['url']; ?>"><?php echo $lang_staffpanel[$row['name']] ?? $row['name']; ?></a></strong></td>
    <td class=rowfollow align=left><?php echo $lang_staffpanel[$row['info']] ?? $row['info']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<br /><br />
<?php
endif;

if (! empty($modPanels)):
?>
<h1 align=center>..:: <?php echo $lang_staffpanel['For Moderator Only'] ?? 'For Moderator Only'; ?> ::..</h1>
<br /><br />
<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left><?php echo $lang_staffpanel['Option Name'] ?? 'Option Name'; ?></td><td class=colhead align=left><?php echo $lang_staffpanel['Info'] ?? 'Info'; ?></td></tr>
<?php foreach ($modPanels as $row): ?>
<tr>
    <td class=rowfollow align=left><strong><a href="<?php echo $row['url']; ?>"><?php echo $lang_staffpanel[$row['name']] ?? $row['name']; ?></a></strong></td>
    <td class=rowfollow align=left><?php echo $lang_staffpanel[$row['info']] ?? $row['info']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<br /><br />
<?php
endif;

?>
