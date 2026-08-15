<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_functions)) $lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);

$perpage = (int) ($perpage ?? 20);
$count = (int) ($count ?? 0);
$rows = (array) ($rows ?? []);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$userDisplayMap = (array) ($userDisplayMap ?? []);

if ($count == 0) {
    \App\Support\Html::stdMessage($lang_functions['text_sorry'] ?? 'Sorry', $lang_functions['text_no_comments'] ?? 'No comments yet.');
} else {
    print($pagertop);
    ?>
    <h1 align="center"><?php echo $lang_functions['text_latest_comments'] ?? 'Latest Comments'; ?></h1>
    <?php
    foreach ($rows as $row) {
        $row = (array) $row;
        $userId = (int) ($row['user'] ?? 0);
        $commentId = (int) ($row['id'] ?? 0);
        $parentType = (string) ($row['parent_type'] ?? '');
        $parentId = (int) ($row['parent_id'] ?? 0);
        $parentName = (string) ($row['parent_name'] ?? '');

        $parentUrl = '';
        if ($parentType === 'torrent' && $parentId > 0) {
            $parentUrl = "details.php?id={$parentId}&hit=1#cid{$commentId}";
        } elseif ($parentType === 'offer' && $parentId > 0) {
            $parentUrl = "offers.php?id={$parentId}&off_details=1#cid{$commentId}";
        }

        $avatar = ($CURUSER['avatars'] ?? '') === 'yes' ? htmlspecialchars(trim((string) ($row['avatar'] ?? ''))) : '';
        if (!$avatar) {
            $avatar = 'pic/default_avatar.png';
        }

        $parentLink = $parentUrl !== '' ? ' <font color="gray">on</font> <a href="' . $parentUrl . '">' . htmlspecialchars($parentName) . '</a>' : '';
        ?>
        <div style="margin-top: 8pt; margin-bottom: 8pt;">
            <table id="cid<?php echo $commentId; ?>" border="0" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="embedded" width="99%">
                        #<?php echo $commentId; ?>&nbsp;&nbsp;
                        <font color="gray"><?php echo $lang_functions['text_by'] ?? 'by'; ?></font>
                        <?php echo $userDisplayMap[$userId] ?? \App\Support\UserDisplay::username($userId, false, true, true, false, false, true); ?>
                        &nbsp;&nbsp;<font color="gray"><?php echo $lang_functions['text_at'] ?? 'at'; ?></font>
                        <?php echo \App\Support\Time::format($row['added'] ?? ''); ?>
                        <?php echo $parentLink; ?>
                    </td>
                </tr>
            </table>
            <table class="main" width="100%" border="0" cellspacing="0" cellpadding="5">
                <tr>
                    <td class="rowfollow" width="150" valign="top" style="padding: 0px;">
                        <?php echo \App\Support\UserDisplay::avatarImageWithContext($avatar); ?>
                    </td>
                    <td class="rowfollow word-break-all" valign="top">
                        <br />
                        <?php echo \App\Support\Format::formatComment((string) ($row['text'] ?? '')); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    print($pagerbottom);
}
