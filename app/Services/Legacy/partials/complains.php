<?php

use App\Support\Captcha;
use App\Support\Format;
use App\Support\Html;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserDisplay;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($lang_complains)) {
    $lang_complains = (array) (SupportContext::getGlobal('lang_complains') ?? []);
}
if (! isset($lang_functions)) {
    $lang_functions = (array) (SupportContext::getGlobal('lang_functions') ?? []);
}

$mode = (string) ($mode ?? 'compose');
$isAdmin = (bool) ($isAdmin ?? false);
$isLogin = (bool) ($isLogin ?? false);

if ($mode === 'list') {
    $pendingRows = (array) ($pendingRows ?? []);
    $processedRows = (array) ($processedRows ?? []);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');

    if (($page ?? null) === null) {
        Html::beginFrame($lang_complains['pending_complaints'] ?? 'Pending complaints');
        if (! empty($pendingRows)) {
            echo '<table width="100%">';
            echo Html::tableRow('colhead', $lang_complains['th_complain_at'] ?? 'Added', $lang_complains['th_complain_account'] ?? 'Account', $lang_complains['th_action_view'] ?? 'View');
            foreach ($pendingRows as $row) {
                $row = (array) $row;
                echo Html::tableRow('rowfollow', Time::format($row['added'] ?? ''), htmlspecialchars((string) ($row['email'] ?? '')), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', htmlspecialchars((string) ($row['uuid'] ?? '')), $lang_complains['th_action_view'] ?? 'View'));
            }
            echo '</table>';
        } else {
            echo $lang_complains['no_pending_complaints'] ?? 'No pending complaints.';
        }
        Html::endFrame();
    }

    Html::beginFrame($lang_complains['complaints_processed'] ?? 'Processed complaints');
    if (! empty($processedRows)) {
        echo $pagertop;
        echo '<table width="100%">';
        echo Html::tableRow('colhead', $lang_complains['th_complain_at'] ?? 'Added', $lang_complains['th_complain_account'] ?? 'Account', $lang_complains['th_action_view'] ?? 'View');
        foreach ($processedRows as $row) {
            $row = (array) $row;
            echo Html::tableRow('rowfollow', Time::format($row['added'] ?? ''), htmlspecialchars((string) ($row['email'] ?? '')), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', htmlspecialchars((string) ($row['uuid'] ?? '')), $lang_complains['th_action_view'] ?? 'View'));
        }
        echo '</table>';
        echo $pagerbottom;
    } else {
        echo $lang_complains['no_complaints_have_been_processed'] ?? 'No complaints have been processed.';
    }
    Html::endFrame();
} elseif ($mode === 'view') {
    $complain = (array) ($complain ?? []);
    $user = (array) ($user ?? []);
    $replyRows = (array) ($replyRows ?? []);
    $replyUserMap = (array) ($replyUserMap ?? []);

    if (! $isLogin) {
        Html::beginFrame($lang_complains['text_created_title'] ?? 'Created');
        printf('<p style="font-weight: bold; color: red">%s</p>', $lang_complains['text_created_note'] ?? '');
        Html::endFrame();
    }

    Html::beginFrame($lang_complains['text_new_body'] ?? 'Body');
    printf('%s：%s<br />%s %s', $lang_complains['text_added'] ?? 'Added', Time::format($complain['added'] ?? ''), $lang_complains['text_new_email'] ?? 'Email', htmlspecialchars((string) ($complain['email'] ?? '')));
    if ($isAdmin) {
        if (! empty($user)) {
            printf(' [<a href="userdetails.php?id=%s" class="faqlink" target="_blank">%s</a>]', (int) ($user['id'] ?? 0), htmlspecialchars((string) ($user['username'] ?? '')));
            printf(' [<a href="user-ban-log.php?q=%s" class="faqlink" target="_blank">%s</a>]', urlencode((string) ($user['username'] ?? '')), $lang_complains['text_view_band_log'] ?? 'View ban log');
        } else {
            printf(' [<a href="usersearch.php?em=%s" class="faqlink" target="_blank">%s</a>]', urlencode((string) ($complain['email'] ?? '')), $lang_complains['text_search_account'] ?? 'Search account');
        }
        printf('<br />IP: '.htmlspecialchars((string) ($complain['ip'] ?? '')));
    }
    echo '<hr />', Format::formatComment($complain['body'] ?? '');
    Html::endFrame();

    Html::beginFrame($lang_complains['text_replies'] ?? 'Replies');
    if (! empty($replyRows)) {
        foreach ($replyRows as $r) {
            $row = (array) $r;
            printf('<b>%s @ %s', (int) ($row['userid'] ?? 0) ? ($replyUserMap[(int) ($row['userid'] ?? 0)] ?? UserDisplay::plainUsername((int) ($row['userid'] ?? 0))) : ($lang_complains['text_complainer'] ?? 'Complainer'), Time::format($row['added'] ?? ''));
            if ($isAdmin) {
                printf(' (%s)', htmlspecialchars((string) ($row['ip'] ?? '')));
            }
            echo ': </b>';
            echo Format::formatComment($row['body'] ?? '').'<hr />';
        }
    } else {
        printf('<p align="center">%s</p>', $lang_complains['text_no_replies'] ?? 'No replies.');
    }
    Html::endFrame();

    if (! empty($complain['answered']) && (int) $complain['answered'] !== 0) {
        printf('<p align="center">%s</p>', $lang_complains['text_closed'] ?? 'This complain has been closed.');
    } else {
        printf('<br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>%s</b><br /><br /><form id="reply" method="post" action="" onsubmit="return postvalid(this);"><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="%u" /><br />', $lang_complains['text_reply'] ?? 'Reply', (int) ($complain['id'] ?? 0));
        Html::quickReplyVoid('reply', 'body', $lang_complains['text_reply'] ?? 'Reply');
        echo '</form></td></tr></table>';
    }

    if ($isAdmin) {
        printf('<form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="%s" /><input type="hidden" name="id" value="%u" /><button>%s</button></form>', ! empty($complain['answered']) ? 'unanswered' : 'answered', (int) ($complain['id'] ?? 0), ! empty($complain['answered']) ? ($lang_complains['text_unanswer_it'] ?? 'Reopen') : ($lang_complains['text_answer_it'] ?? 'Close'));
    }
} else {
    ?>
    <h2><?= $lang_complains['text_new_complain'] ?? 'New complain' ?></h2>
    <form action="" method="post">
        <input type="hidden" name="action" value="new" />
        <?php
        $inputStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';
    $textareaStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;"';
    ?>
        <table border="0" cellpadding="5">
            <tr><td class="rowhead"><?php echo $lang_complains['text_new_email'] ?? 'Email' ?></td><td class="rowfollow" align="left"><input type="email" name="email" <?php echo $inputStyle; ?> autocomplete="email" /></td></tr>
            <tr><td class="rowhead"><?php echo $lang_complains['text_new_body'] ?? 'Body' ?></td><td class="rowfollow" align="left"><textarea name="body" <?php echo $textareaStyle; ?> placeholder="<?= $lang_complains['text_new_body_placeholder'] ?? '' ?>"></textarea></td></tr>
            <?php Captcha::showImageCode(); ?>
            <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?= $lang_complains['text_new_submit'] ?? 'Submit' ?>" class="btn" /></td></tr>
        </table>
    </form>
    <?php
}
