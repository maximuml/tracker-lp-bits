<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_sendmessage)) $lang_sendmessage = (array) (\App\Support\SupportContext::getGlobal('lang_sendmessage') ?? []);

$receiver = (int) ($receiver ?? 0);
$replyto = (int) ($replyto ?? 0);
$subject = (string) ($subject ?? '');
$body = (string) ($body ?? '');
$returnto = (string) ($returnto ?? '');
$title = (string) ($title ?? ($lang_sendmessage['head_send_message'] ?? 'Send message'));

$deleteChecked = ($CURUSER['deletepms'] ?? '') == 'yes' ? ' checked' : '';
$saveChecked = ($CURUSER['savepms'] ?? '') == 'yes' ? ' checked' : '';
?>

<form id="compose" name="compose" method="post" action="takemessage.php">
<?php echo csrf_field(); ?>
<input type="hidden" name="receiver" value="<?php echo $receiver; ?>">
<?php if ($returnto !== ''): ?>
    <input type="hidden" name="returnto" value="<?php echo $returnto; ?>">
<?php endif; ?>
<?php
\App\Support\Frame::composeBeginVoid($title, ($replyto ? "reply" : "new"), $body, true, $subject);
?>
<tr><td class="toolbox" colspan="2" align="center">
<?php if ($replyto): ?>
    <input type="checkbox" name="delete" value="yes" <?php echo $deleteChecked; ?>> <?php echo $lang_sendmessage['checkbox_delete_message_replying_to'] ?? 'Delete message replying to'; ?>
    <input type="hidden" name="origmsg" value="<?php echo $replyto; ?>">
<?php endif; ?>
    <input type="checkbox" name="save" value="yes" <?php echo $saveChecked; ?>> <?php echo $lang_sendmessage['checkbox_save_message_to_sendbox'] ?? 'Save message to sendbox'; ?>
</td></tr>
<?php
\App\Support\Frame::composeEndVoid();
?>
</form>
