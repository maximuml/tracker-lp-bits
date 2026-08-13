<?php
\App\Support\Html::stdhead($lang_staff['head_staff'] ?? 'Staff');
\App\Support\Frame::mainFrameOpen();
?>

<?php \App\Support\Html::beginFrame(($lang_staff['text_firstline_support'] ?? 'Firstline Support') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>'); ?>
<?php echo $lang_staff['text_firstline_support_note'] ?? ''; ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b><?php echo $lang_staff['text_username'] ?? 'Username'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?? 'Country'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?? 'Online/Offline'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?? 'Contact'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_language'] ?? 'Language'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_support_for'] ?? 'Support for'; ?></b></td>
    </tr>
    <tr><td class=embedded colspan=6><hr color="#4040c0"></td></tr>
    <?php foreach ($supportRows as $row): ?>
    <tr>
        <td class=embedded><?php echo $row['username_html']; ?></td>
        <td class=embedded><?php echo $row['flag_html']; ?></td>
        <td class=embedded><?php echo $row['online_html']; ?></td>
        <td class=embedded><?php echo $row['pm_html']; ?></td>
        <td class=embedded><?php echo htmlspecialchars((string) $row['extra']); ?></td>
        <td class=embedded><?php echo htmlspecialchars((string) $row['extra']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php \App\Support\Html::endFrame(); ?>

<?php \App\Support\Html::beginFrame(($lang_staff['text_movie_critics'] ?? 'Movie Critics') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>'); ?>
<?php echo $lang_staff['text_movie_critics_note'] ?? ''; ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b><?php echo $lang_staff['text_username'] ?? 'Username'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?? 'Country'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?? 'Online/Offline'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?? 'Contact'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_responsible_for'] ?? 'Responsible for'; ?></b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    <?php foreach ($pickerRows as $row): ?>
    <tr>
        <td class=embedded><?php echo $row['username_html']; ?></td>
        <td class=embedded><?php echo $row['flag_html']; ?></td>
        <td class=embedded><?php echo $row['online_html']; ?></td>
        <td class=embedded><?php echo $row['pm_html']; ?></td>
        <td class=embedded><?php echo htmlspecialchars((string) $row['extra']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php \App\Support\Html::endFrame(); ?>

<?php \App\Support\Html::beginFrame(($lang_staff['text_forum_moderators'] ?? 'Forum Moderators') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>'); ?>
<?php echo $lang_staff['text_forum_moderators_note'] ?? ''; ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b><?php echo $lang_staff['text_username'] ?? 'Username'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?? 'Country'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?? 'Online/Offline'; ?></b></td>
        <td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?? 'Contact'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_forums'] ?? 'Forums'; ?></b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    <?php foreach ($forumModRows as $row): ?>
    <tr>
        <td class=embedded><?php echo $row['username_html']; ?></td>
        <td class=embedded><?php echo $row['flag_html']; ?></td>
        <td class=embedded><?php echo $row['online_html']; ?></td>
        <td class=embedded><?php echo $row['pm_html']; ?></td>
        <td class=embedded><?php echo $row['forums_html']; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php \App\Support\Html::endFrame(); ?>

<?php \App\Support\Html::beginFrame(($lang_staff['text_general_staff'] ?? 'General Staff') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>'); ?>
<?php echo $lang_staff['text_general_staff_note'] ?? ''; ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
    <?php foreach ($staffRows as $row): ?>
        <?php if (isset($row['header'])): ?>
            <?php if ($row !== reset($staffRows)): ?><tr height=15><td class=embedded colspan=5 align=right>&nbsp;</td></tr><?php endif; ?>
            <tr height=15><td class=embedded colspan=5 align=right><?php echo $row['class_name']; ?></td></tr>
            <tr>
                <td class=embedded><b><?php echo $lang_staff['text_username'] ?? 'Username'; ?></b></td>
                <td class=embedded align=center><b><?php echo $lang_staff['text_country'] ?? 'Country'; ?></b></td>
                <td class=embedded align=center><b><?php echo $lang_staff['text_online_or_offline'] ?? 'Online/Offline'; ?></b></td>
                <td class=embedded align=center><b><?php echo $lang_staff['text_contact'] ?? 'Contact'; ?></b></td>
                <td class=embedded><b><?php echo $lang_staff['text_duties'] ?? 'Duties'; ?></b></td>
            </tr>
            <tr height=15><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
        <?php else: ?>
            <tr>
                <td class=embedded><?php echo $row['username_html']; ?></td>
                <td class=embedded><?php echo $row['flag_html']; ?></td>
                <td class=embedded><?php echo $row['online_html']; ?></td>
                <td class=embedded><?php echo $row['pm_html']; ?></td>
                <td class=embedded><?php echo htmlspecialchars((string) $row['extra']); ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
</table>
<?php \App\Support\Html::endFrame(); ?>

<?php \App\Support\Html::beginFrame($lang_staff['text_vip'] ?? 'VIP'); ?>
<?php echo sprintf($lang_staff['text_vip_note'] ?? '%s', $siteName); ?>
<br /><br />
<table width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b><?php echo $lang_staff['text_username'] ?? 'Username'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_country'] ?? 'Country'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_online_or_offline'] ?? 'Online/Offline'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_contact'] ?? 'Contact'; ?></b></td>
        <td class=embedded><b><?php echo $lang_staff['text_reason'] ?? 'Reason'; ?></b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    <?php foreach ($vipRows as $row): ?>
    <tr>
        <td class=embedded><?php echo $row['username_html']; ?></td>
        <td class=embedded><?php echo $row['flag_html']; ?></td>
        <td class=embedded><?php echo $row['online_html']; ?></td>
        <td class=embedded><?php echo $row['pm_html']; ?></td>
        <td class=embedded><?php echo htmlspecialchars((string) $row['extra']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php \App\Support\Html::endFrame(); ?>

<?php
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
