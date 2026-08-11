<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
stdhead($lang_functions['text_latest_comments'] ?? 'Latest Comments');
begin_main_frame();

$perpage = 20;
$count = \App\Repositories\CommentRepository::countLatest();

if ($count == 0) {
    stdmsg($lang_functions['text_sorry'] ?? 'Sorry', $lang_functions['text_no_comments'] ?? 'No comments yet.');
} else {
    [$pagertop, $pagerbottom, , $offset, $perpage] = pager($perpage, $count, 'latestcomments.php?');
    $rows = \App\Repositories\CommentRepository::getLatest($perpage, $offset);

    $uidArr = array_unique(array_column($rows, 'user'));
    $neededColumns = ['id', 'username', 'avatar', 'donor', 'warned', 'enabled', 'title', 'class', 'leechwarn'];
    $userInfoArr = \App\Models\User::query()->find($uidArr, $neededColumns)->keyBy('id');

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

        $userInfo = $userInfoArr->get($userId);
        $userRow = $userInfo ? $userInfo->toArray() : [];
        $avatar = ($CURUSER['avatars'] ?? '') === 'yes' ? htmlspecialchars(trim((string) ($userRow['avatar'] ?? ''))) : '';
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
                        <?php echo get_username($userId, false, true, true, false, false, true); ?>
                        &nbsp;&nbsp;<font color="gray"><?php echo $lang_functions['text_at'] ?? 'at'; ?></font>
                        <?php echo \App\Support\Time::format($row['added'] ?? ''); ?>
                        <?php echo $parentLink; ?>
                    </td>
                </tr>
            </table>
            <table class="main" width="100%" border="0" cellspacing="0" cellpadding="5">
                <tr>
                    <td class="rowfollow" width="150" valign="top" style="padding: 0px;">
                        <?php echo return_avatar_image($avatar); ?>
                    </td>
                    <td class="rowfollow word-break-all" valign="top">
                        <br />
                        <?php echo format_comment((string) ($row['text'] ?? '')); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    print($pagerbottom);
}

end_main_frame();
stdfoot();
