<?php
if ($mode === 'list'):
    ?>
    <h1 align=center><?php echo $lang_staffbox['text_staff_pm'] ?? 'Staff PM'; ?></h1>
    <?php
    if (empty($rows)) {
        \App\Support\Html::stdMessage($lang_staffbox['std_sorry'] ?? 'Sorry', $lang_staffbox['std_no_messages_yet'] ?? 'No messages yet.');
    } else {
        ?>
        <form method=post action="staffbox.php?action=takecontactanswered">
            @csrf
            <table width=940 border=1 cellspacing=0 cellpadding=5 align=center>
            <tr>
                <td class=colhead align=left><?php echo $lang_staffbox['col_subject'] ?? 'Subject'; ?></td>
                <td class=colhead align=center><?php echo $lang_staffbox['col_sender'] ?? 'Sender'; ?></td>
                <td class=colhead align=center><nobr><?php echo $lang_staffbox['col_added'] ?? 'Added'; ?></nobr></td>
                <td class=colhead align=center><?php echo $lang_staffbox['col_answered'] ?? 'Answered'; ?></td>
                <td class=colhead align=center><nobr><?php echo $lang_staffbox['col_action'] ?? 'Action'; ?></nobr></td>
            </tr>
            <?php foreach ($rows as $arr): ?>
            <?php
                if ($arr['answered']) {
                    $answered = '<nobr><font color=green>' . ($lang_staffbox['text_yes'] ?? 'Yes') . '</font> - ' . \App\Support\UserDisplay::username($arr['answeredby']) . '</nobr>';
                } else {
                    $answered = '<font color=red>' . ($lang_staffbox['text_no'] ?? 'No') . '</font>';
                }
                $pmid = (int) $arr['id'];
            ?>
            <tr>
                <td width=100% class=rowfollow align=left><a href="staffbox.php?action=viewpm&pmid=<?php echo $pmid; ?>&return=<?php echo urlencode($queryString); ?>"><?php echo htmlspecialchars((string) $arr['subject']); ?></a></td>
                <td class=rowfollow align=center><?php echo \App\Support\UserDisplay::username($arr['sender']); ?></td>
                <td class=rowfollow align=center><nobr><?php echo \App\Support\Time::format($arr['added'], true, false); ?></nobr></td>
                <td class=rowfollow align=center><?php echo $answered; ?></td>
                <td class=rowfollow align=center><input type="checkbox" name="setanswered[]" value="<?php echo $pmid; ?>" /></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td class=rowfollow align=right colspan=5>
                    <input type="button" value="<?php echo $lang_functions['input_check_all'] ?? 'Check all'; ?>" onclick="this.value=check(form, '<?php echo $lang_functions['input_check_all'] ?? 'Check all'; ?>', '<?php echo $lang_functions['input_uncheck_all'] ?? 'Uncheck all'; ?>')"/>
                    <input type="submit" name="setdealt" value="<?php echo $lang_staffbox['submit_set_answered'] ?? 'Set answered'; ?>" />
                    <input type="submit" name="delete" value="<?php echo $lang_staffbox['submit_delete'] ?? 'Delete'; ?>" />
                </td>
            </tr>
            </table>
        </form>
        <?php echo $pagerbottom; ?>
        <?php
    }

elseif ($mode === 'viewpm'):
    $colspan = $arr['answered'] == 1 ? '3' : '2';
    $width = $arr['answered'] == 1 ? '33' : '50';
    $subject = htmlspecialchars((string) $arr['subject']);
    ?>
    <h1 align="center"><a class="faqlink" href="staffbox.php"><?php echo $lang_staffbox['text_staff_pm'] ?? 'Staff PM'; ?></a>--><?php echo $subject; ?></h1>
    <table width="737" border="0" cellpadding="4" cellspacing="0">
    <tr>
        <td width="<?php echo $width; ?>%" class="colhead" align="left"><?php echo $lang_staffbox['col_from'] ?? 'From'; ?></td>
        <?php if ($arr['answered'] == 1): ?>
        <td width="34%" class="colhead" align="left"><?php echo $lang_staffbox['col_answered_by'] ?? 'Answered by'; ?></td>
        <?php endif; ?>
        <td width="<?php echo $width; ?>%" class="colhead" align="left"><?php echo $lang_staffbox['col_date'] ?? 'Date'; ?></td>
    </tr>
    <tr>
        <td class="rowfollow" align="left"><?php echo $sender; ?></td>
        <?php if ($arr['answered'] == 1): ?>
        <td class="rowfollow" align="left"><?php echo $answeredby; ?></td>
        <?php endif; ?>
        <td class="rowfollow" align="left"><?php echo \App\Support\Time::format($arr['added']); ?></td>
    </tr>
    <tr><td colspan="<?php echo $colspan; ?>" align="left"><?php echo \App\Support\Format::formatComment($arr['msg']); ?></td></tr>
    <?php if ($arr['answered'] == 1 && $arr['answer']): ?>
    <tr><td colspan="<?php echo $colspan; ?>" align="left"><?php echo \App\Support\Format::formatComment($arr['answer']); ?></td></tr>
    <?php endif; ?>
    <tr><td colspan="<?php echo $colspan; ?>" align="right">
    <font color=white>
    <?php if ($arr['answered'] == 0): ?>
    [ <a href="staffbox.php?action=answermessage&receiver=<?php echo (int) $arr['sender']; ?>&answeringto=<?php echo (int) $arr['id']; ?>"><?php echo $lang_staffbox['text_reply'] ?? 'Reply'; ?></a> ] [ <a href="staffbox.php?action=setanswered&id=<?php echo (int) $arr['id']; ?>&return=<?php echo urlencode((string) ($__server_QUERY_STRING ?? '')); ?>"><?php echo $lang_staffbox['text_mark_answered'] ?? 'Mark answered'; ?></a> ]
    <?php endif; ?>
    [ <a href="staffbox.php?action=deletestaffmessage&id=<?php echo (int) $arr['id']; ?>"><?php echo $lang_staffbox['text_delete'] ?? 'Delete'; ?></a> ]
    </font>
    </td></tr>
    </table>
    <?php

elseif ($mode === 'answermessage'):
    ?>
    <form method="post" id="compose" name="message" action="staffbox.php?action=takeanswer">
        @csrf
        <?php if ($returnTo): ?>
        <input type=hidden name=returnto value="<?php echo htmlspecialchars((string) $returnTo); ?>">
        <?php endif; ?>
        <input type=hidden name=receiver value="<?php echo (int) $receiver; ?>">
        <input type=hidden name=answeringto value="<?php echo (int) $answeringto; ?>">
        <?php
        $title = ($lang_staffbox['text_answering_to'] ?? 'Answering to') . '<a href="staffbox.php?action=viewpm&pmid=' . (int) $staffmsg['id'] . '">' . htmlspecialchars((string) $staffmsg['subject']) . '</a>' . ($lang_staffbox['text_sent_by'] ?? ' sent by ') . \App\Support\UserDisplay::username($staffmsg['sender']);
        \App\Support\Frame::composeBeginVoid($title, 'reply', '', false);
        \App\Support\Frame::composeEndVoid();
        ?>
    </form>
    <?php
endif;
?>
