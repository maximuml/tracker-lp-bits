<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_unco)) $lang_unco = (array) (\App\Support\SupportContext::getGlobal('lang_unco') ?? []);
?>
<?php
        $title = 'Unconfirmed Users';
        \App\Support\Html::stdhead($title);
        \App\Support\Frame::mainFrameOpen();
        ?>
        <?php
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Sorry', 'Access denied.');
}
$status = \App\Support\SupportContext::getQuery('status');
if ($status) {
    \App\Support\LegacyResponse::assertId($status, true);
}
$rows = \App\Models\User::query()->where('status', 'pending')->orderBy('username')->get();
?>

<?php if ($rows->isNotEmpty()): ?>
    <?php \App\Support\Html::beginFrame(''); ?>
    <table width="100%" border="1" cellspacing="0" cellpadding="5">
        <?php if ($status): ?>
            <tr>
                <td class="rowhead" colspan="5"><font color="red" size="1">The User account has been updated!</font></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td class="rowhead"><center>Name</center></td>
            <td class="rowhead"><center>eMail</center></td>
            <td class="rowhead"><center>Added</center></td>
            <td class="rowhead"><center>Set Status</center></td>
            <td class="rowhead"><center>Confirm</center></td>
        </tr>
        <?php foreach ($rows as $userRow): ?>
            <?php
            $row = $userRow->getAttributes();
            $id = $row['id'];
            ?>
            <tr>
                <form method="post" action="modtask.php">
                    <input type="hidden" name="action" value="confirmuser">
                    <input type="hidden" name="userid" value="<?php echo htmlspecialchars((string) ( $id ), ENT_QUOTES, 'UTF-8'); ?>">
                    <a href="userdetails.php?id=<?php echo htmlspecialchars((string) ( $row['id'] ), ENT_QUOTES, 'UTF-8'); ?>"><td><center><?php echo htmlspecialchars((string) ( $row['username'] ), ENT_QUOTES, 'UTF-8'); ?></center></td></a>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars((string) ( $row['email'] ), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars((string) ( $row['added'] ), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td align="center">
                        <select name="confirm">
                            <option value="pending">pending</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                    </td>
                    <td align="center"><input type="submit" value="-Go-" style="height: 20px; width: 40px"></td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php \App\Support\Html::endFrame(); ?>
<?php else: ?>
    <?php if ($status): ?>
        <?php \App\Support\LegacyResponse::abort('Updated!', 'The user account has been updated.'); ?>
    <?php else: ?>
        <?php \App\Support\LegacyResponse::abort('Ups!', 'Nothing Found...'); ?>
    <?php endif; ?>
<?php endif; ?>

        <?php
        \App\Support\Frame::mainFrameClose();
        \App\Support\Html::stdfoot();
        ?>
