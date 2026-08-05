<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (! defined('PM_DELETED')) {
    define('PM_DELETED', 0);
}
if (! defined('PM_INBOX')) {
    define('PM_INBOX', 1);
}

$id = $_GET["id"] ?? 0;
if (!is_numeric($id) || $id < 1 || floor($id) != $id)
    stderr("Error", $lang_deletemessage['std_bad_message_id']);

$type = $_GET["type"] ?? '';

if ($type == 'in')
{
    $msg = \App\Models\Message::query()->where('id', $id)->first(['receiver', 'sender', 'location', 'saved', 'unread']);
    if (!$msg)
        stderr("Error", $lang_deletemessage['std_bad_message_id']);
    $arr = $msg->toArray();
    if ($arr["receiver"] != $CURUSER["id"])
        stderr("Error", $lang_deletemessage['std_not_suggested']);

    if ($arr["location"] == PM_DELETED)
        stderr("Error", $lang_deletemessage['std_not_in_inbox']);

    if ($arr["saved"] == 'yes')
    {
        \App\Models\Message::query()->where('id', $id)->update(['location' => PM_DELETED, 'unread' => 'no']);
    }
    else
    {
        \App\Models\Message::query()->where('id', $id)->delete();
    }
    $Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
    $Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
}
elseif ($type == 'out')
{
    $msg = \App\Models\Message::query()->where('id', $id)->first(['receiver', 'sender', 'location', 'saved', 'unread']);
    if (!$msg)
        stderr("Error", $lang_deletemessage['std_bad_message_id']);
    $arr = $msg->toArray();
    if ($arr["sender"] != $CURUSER["id"])
        stderr("Error", $lang_deletemessage['std_not_suggested']);

    if ($arr["location"] == PM_DELETED && $arr["saved"] == 'no')
        stderr("Error", $lang_deletemessage['std_not_in_sentbox']);

    if ($arr["location"] == PM_DELETED)
    {
        \App\Models\Message::query()->where('id', $id)->delete();
    }
    else
    {
        \App\Models\Message::query()->where('id', $id)->update(['saved' => 'no']);
    }
    $Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
else
    stderr("Error", $lang_deletemessage['std_unknown_pm_type']);

header("Location: " . get_protocol_prefix() . "$BASEURL/messages.php" . ($type == 'out' ? "?out=1" : ""));
return;
?>
