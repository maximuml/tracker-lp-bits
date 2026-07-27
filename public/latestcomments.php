<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

stdhead($lang_functions['text_latest_comments'] ?? 'Latest Comments');
begin_main_frame();

$perpage = 20;
$count = \App\Repositories\CommentRepository::countLatest();

if ($count == 0) {
    stdmsg($lang_functions['text_sorry'] ?? 'Sorry', $lang_functions['text_no_comments'] ?? 'No comments yet.');
} else {
    [$pagertop, $pagerbottom, , $offset, $perpage] = pager($perpage, $count, 'latestcomments.php?');
    $rows = \App\Repositories\CommentRepository::getLatest($perpage, $offset);

    print($pagertop);
    ?>
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
    <tr>
        <td class="colhead"><?php echo $lang_functions['col_date'] ?? 'Date'; ?></td>
        <td class="colhead"><?php echo $lang_functions['col_user'] ?? 'User'; ?></td>
        <td class="colhead"><?php echo $lang_functions['col_parent'] ?? 'Parent'; ?></td>
        <td class="colhead"><?php echo $lang_functions['col_comment'] ?? 'Comment'; ?></td>
    </tr>
    <?php
    foreach ($rows as $row) {
        $row = (array) $row;
        $parentType = (string) ($row['parent_type'] ?? '');
        $parentId = (int) ($row['parent_id'] ?? 0);
        $parentName = (string) ($row['parent_name'] ?? '');
        $commentId = (int) ($row['id'] ?? 0);

        $parentUrl = '';
        if ($parentType === 'torrent' && $parentId > 0) {
            $parentUrl = "details.php?id={$parentId}&hit=1#cid{$commentId}";
        } elseif ($parentType === 'offer' && $parentId > 0) {
            $parentUrl = "offers.php?id={$parentId}&off_details=1#cid{$commentId}";
        }

        $date = gettime($row['added'] ?? '');
        $user = get_username($row['user'] ?? 0, false, true, true, false, false, true);

        $text = strip_tags(format_comment((string) ($row['text'] ?? '')));
        if (function_exists('mb_strlen') && mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200) . '...';
        } elseif (strlen($text) > 200) {
            $text = substr($text, 0, 200) . '...';
        }
    ?>
    <tr>
        <td class="rowfollow nowrap"><?php echo $date; ?></td>
        <td class="rowfollow"><?php echo $user; ?></td>
        <td class="rowfollow"><?php echo $parentUrl !== '' ? '<a href="' . $parentUrl . '">' . htmlspecialchars($parentName) . '</a>' : htmlspecialchars($parentName); ?></td>
        <td class="rowfollow"><?php echo htmlspecialchars($text); ?></td>
    </tr>
    <?php
    }
    ?>
</table>
    <?php
    print($pagerbottom);
}

end_main_frame();
stdfoot();
