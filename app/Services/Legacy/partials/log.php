<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_log)) $lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);

$mode = (string) ($mode ?? 'dailylog');
$canPollManage = (bool) ($canPollManage ?? false);

echo '<div id="lognav"><ul id="logmenu" class="menu">';
foreach (['dailylog' => ($lang_log['text_daily_log'] ?? 'Daily log'), 'chronicle' => ($lang_log['text_chronicle'] ?? 'Chronicle'), 'news' => ($lang_log['text_news'] ?? 'News'), 'poll' => ($lang_log['text_poll'] ?? 'Poll')] as $a => $label) {
    echo '<li' . ($mode === $a ? ' class=selected' : '') . '><a href="?action=' . $a . '">' . $label . '</a></li>';
}
echo '</ul></div>';

if ($mode === 'dailylog') {
    $q = (string) ($q ?? '');
    $search = (string) ($search ?? '');
    $canConfidentialLog = (bool) ($canConfidentialLog ?? false);
    $logRows = (array) ($logRows ?? []);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');
    $userDisplayMap = (array) ($userDisplayMap ?? []);

    $opts = ['all' => ($lang_log['text_all'] ?? 'All'), 'normal' => ($lang_log['text_normal'] ?? 'Normal'), 'mod' => ($lang_log['text_mod'] ?? 'Mod')];
    ?>
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left><?php echo $lang_log['text_search_log'] ?? 'Search log'; ?></td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="<?php echo $q; ?>">
                <?php if ($canConfidentialLog): ?>
                    <?php echo $lang_log['text_in'] ?? 'in'; ?><select name="search">
                    <?php foreach ($opts as $value => $text): ?>
                        <option value='<?php echo $value; ?>'<?php echo $value === $search ? ' selected' : ''; ?>><?php echo $text; ?></option>
                    <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <input type="hidden" name="action" value="dailylog">
                &nbsp;&nbsp;<input type=submit value="<?php echo $lang_log['submit_search'] ?? 'Search'; ?>"></form>
        </td></tr>
    </table><br />
    <?php
    if (empty($logRows)) {
        print($lang_log['text_log_empty'] ?? 'Log is empty.');
    } else {
        print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
        print("<tr><td class=colhead align=center><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"" . ($lang_log['title_time_added'] ?? 'Time added') . "\" /></td><td class=colhead align=left>" . ($lang_log['col_event'] ?? 'Event') . "");
        if ($canConfidentialLog) {
            print("<td class=colhead align=left>" . ($lang_log['col_user'] ?? 'User') . "</td>");
        }
        print("</td></tr>\n");
        foreach ($logRows as $arr) {
            $color = '';
            $txt = (string) ($arr['txt'] ?? '');
            if (strpos($txt, 'was uploaded by') !== false) $color = 'green';
            if (strpos($txt, 'was deleted by') !== false) $color = 'red';
            if (strpos($txt, 'was added to the Request section') !== false) $color = 'purple';
            if (strpos($txt, 'was edited by') !== false) $color = 'blue';
            if (strpos($txt, 'settings updated by') !== false) $color = 'darkred';
            print("<tr><td class=\"rowfollow nowrap\" align=center>" . \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false) . "</td><td class=rowfollow align=left><font color='" . $color . "'>" . htmlspecialchars($txt) . "</font></td>");
            if ($canConfidentialLog) {
                $uid = (int) ($arr['uid'] ?? 0);
                print("<td class=rowfollow align=left>" . ($uid > 0 ? ($userDisplayMap[$uid] ?? \App\Support\UserDisplay::username($uid)) : "System") . "</td>");
            }
            print("</tr>\n");
        }
        print("</table>");
        echo $pagerbottom;
    }
    print($lang_log['time_zone_note'] ?? '');
} elseif ($mode === 'chronicle') {
    $q = (string) ($q ?? '');
    $canManage = (bool) ($canManage ?? false);
    $chronicleRows = (array) ($chronicleRows ?? []);
    $editItem = (array) ($editItem ?? []);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');
    ?>
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left><?php echo $lang_log['text_search_chronicle'] ?? 'Search chronicle'; ?></td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="<?php echo $q; ?>">
                <input type="hidden" name="action" value="chronicle">
                &nbsp;&nbsp;<input type=submit value="<?php echo $lang_log['submit_search'] ?? 'Search'; ?>"></form>
        </td></tr>
    </table><br />
    <?php
    if ($canManage) {
        $title = $lang_log['text_add_chronicle'] ?? 'Add chronicle';
        $value = $title;
        $do = 'add';
        $editId = '';
        if (! empty($editItem)) {
            $title = $lang_log['text_edit_chronicle'] ?? 'Edit chronicle';
            $value = (string) ($editItem['txt'] ?? '');
            $do = 'update';
            $editId = '<input type="hidden" name="id" value="' . (int) ($editItem['id'] ?? 0) . '">';
        }
        ?>
        <table border=1 cellspacing=0 width=940 cellpadding=5>
            <tr><td class=colhead align=left><?php echo $title; ?></td></tr>
            <tr><td class=toolbox align=left>
                <form method="post" action="">
                    <textarea name="txt" style="width:500px" rows="3"><?php echo htmlspecialchars($value); ?></textarea>
                    <input type="hidden" name="action" value="chronicle">
                    <input type="hidden" name="do" value="<?php echo $do; ?>">
                    <?php echo $editId; ?>
                    <input type=submit value="<?php echo $lang_log['submit_add'] ?? 'Add'; ?>"></form>
            </td></tr>
        </table><br />
        <?php
    }
    if (empty($chronicleRows)) {
        print($lang_log['text_chronicle_empty'] ?? 'Chronicle is empty.');
    } else {
        print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
        print("<tr><td class=colhead align=center>" . ($lang_log['col_date'] ?? 'Date') . "</td><td class=colhead align=left>" . ($lang_log['col_event'] ?? 'Event') . "</td>" . ($canManage ? "<td class=colhead align=center>" . ($lang_log['col_modify'] ?? 'Modify') . "</td>" : '') . "</tr>\n");
        foreach ($chronicleRows as $arr) {
            $date = \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false);
            print("<tr><td class=rowfollow align=center><nobr>$date</nobr></td><td class=rowfollow align=left>" . \App\Support\Format::formatComment((string) ($arr['txt'] ?? ''), true, false, true) . "</td>" . ($canManage ? "<td align=center nowrap><b><a href=\"?action=chronicle&do=edit&id=" . (int) ($arr['id'] ?? 0) . "\">" . ($lang_log['text_edit'] ?? 'Edit') . "</a>&nbsp;|&nbsp;<a href=\"?action=chronicle&do=del&id=" . (int) ($arr['id'] ?? 0) . "\"><font color=red>" . ($lang_log['text_delete'] ?? 'Delete') . "</font></a></b></td>" : '') . "</tr>\n");
        }
        print("</table>");
        echo $pagerbottom;
    }
    print($lang_log['time_zone_note'] ?? '');
} elseif ($mode === 'news') {
    $q = (string) ($q ?? '');
    $search = (string) ($search ?? '');
    $newsRows = (array) ($newsRows ?? []);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');
    $opts = ['title' => ($lang_log['text_title'] ?? 'Title'), 'body' => ($lang_log['text_body'] ?? 'Body'), 'both' => ($lang_log['text_both'] ?? 'Both')];
    ?>
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left><?php echo $lang_log['text_search_news'] ?? 'Search news'; ?></td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="<?php echo $q; ?>">
                <?php echo $lang_log['text_in'] ?? 'in'; ?><select name="search">
                <?php foreach ($opts as $value => $text): ?>
                    <option value='<?php echo $value; ?>'<?php echo $value === $search ? ' selected' : ''; ?>><?php echo $text; ?></option>
                <?php endforeach; ?>
                </select>
                <input type="hidden" name="action" value="news">
                &nbsp;&nbsp;<input type=submit value="<?php echo $lang_log['submit_search'] ?? 'Search'; ?>"></form>
        </td></tr>
    </table><br />
    <?php
    if (empty($newsRows)) {
        print($lang_log['text_news_empty'] ?? 'No news found.');
    } else {
        foreach ($newsRows as $arr) {
            $date = \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false);
            print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
            print("<tr><td class=rowhead width='10%'>" . ($lang_log['col_title'] ?? 'Title') . "</td><td class=rowfollow align=left>" . htmlspecialchars((string) ($arr['title'] ?? '')) . "</td></tr><tr><td class=rowhead width='10%'>" . ($lang_log['col_date'] ?? 'Date') . "</td><td class=rowfollow align=left>$date</td></tr><tr><td class=rowhead width='10%'>" . ($lang_log['col_body'] ?? 'Body') . "</td><td class=rowfollow align=left>" . \App\Support\Format::formatComment((string) ($arr['body'] ?? ''), false, false, true) . "</td></tr>\n");
            print("</table><br />");
        }
        echo $pagerbottom;
    }
    print($lang_log['time_zone_note'] ?? '');
} elseif ($mode === 'poll') {
    $pollData = (array) ($pollData ?? []);
    ?>
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=center><?php echo $lang_log['text_previous_polls'] ?? 'Previous polls'; ?></td></tr>
    <?php
    foreach ($pollData as $item) {
        $poll = (array) ($item['poll'] ?? []);
        $added = (string) ($item['added'] ?? '');
        $totalVotes = (string) ($item['totalVotes'] ?? '0');
        $options = (array) ($item['options'] ?? []);
        ?>
        <tr><td align=center>
        <p class=sub><?php echo $added; ?>
        <?php if ($canPollManage): ?>
            - [<a href="makepoll.php?action=edit&pollid=<?php echo (int) ($poll['id'] ?? 0); ?>"><b><?php echo $lang_log['text_edit'] ?? 'Edit'; ?></b></a>]
            - [<a href="?action=poll&do=delete&pollid=<?php echo (int) ($poll['id'] ?? 0); ?>"><b><?php echo $lang_log['text_delete'] ?? 'Delete'; ?></b></a>]
        <?php endif; ?>
        <a name="<?php echo (int) ($poll['id'] ?? 0); ?>"></a></p>
        <table class=main border=1 cellspacing=0 cellpadding=5><tr><td class=text>
        <p align=center><b><?php echo htmlspecialchars((string) ($poll['question'] ?? '')); ?></b></p>
        <table width=100% class=main border=0 cellspacing=0 cellpadding=0>
        <?php foreach ($options as $opt): ?>
            <tr><td class=embedded><?php echo htmlspecialchars((string) ($opt['text'] ?? '')); ?>&nbsp;&nbsp;</td><td class="embedded nowrap"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="unsltbar" src="pic/trans.gif" style="width: <?php echo ((int) ($opt['percent'] ?? 0) * 3); ?>px" /><img class="bar_end" src="pic/trans.gif" alt="" /> <?php echo (int) ($opt['percent'] ?? 0); ?>%</td></tr>
        <?php endforeach; ?>
        </table>
        <p align=center><?php echo $lang_log['text_votes'] ?? 'Votes: '; ?><?php echo $totalVotes; ?></p>
        </td></tr></table><br /><br />
        </td></tr>
        <?php
    }
    ?>
    </table>
    <?php
    print($lang_log['time_zone_note'] ?? '');
}
