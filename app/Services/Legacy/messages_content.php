<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_functions)) $lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);
if (!isset($lang_messages)) $lang_messages = (array) (\App\Support\SupportContext::getGlobal('lang_messages') ?? []);
// Define constants
if (!defined('PM_DELETED')) { define('PM_DELETED',0); } // Message was deleted
if (!defined('PM_INBOX')) { define('PM_INBOX',1); } // Message located in Inbox for reciever
if (!defined('PM_SENTBOX')) { define('PM_SENTBOX',-1); } // GET value for sent box

//----- FUNCTIONS ------
if (!function_exists('insertJumpTo')) { function insertJumpTo($selected = 0)
{
$lang_messages = (array) (\App\Support\SupportContext::getGlobal('lang_messages') ?? []);
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
$pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->orderBy('boxnumber')->get(['boxnumber','name']);
$place = \App\Support\SupportContext::getQuery('place') ?? '';
?>
<form action="messages.php" method="get">
<input type="hidden" name="action" value="viewmailbox"><?php echo $lang_messages['text_search'] ?>&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="<?php echo htmlspecialchars(\App\Support\SupportContext::getQuery('keyword') ?? '')?>" style="width: 200px"/>
<?php echo $lang_messages['text_in'] ?>&nbsp;<select name="place">
<option value="both" <?php echo ($place == 'both' ? " selected" : "")?>><?php echo $lang_messages['select_both'] ?></option>
<option value="title" <?php echo ($place == 'title' ? " selected" : "")?>><?php echo $lang_messages['select_title'] ?></option>
<option value="body" <?php echo ($place == 'body' ? " selected" : "")?>><?php echo $lang_messages['select_body'] ?></option>
</select>
<?php echo $lang_messages['text_jump_to'] ?><select name="box">
<option value="1" <?php echo ($selected == PM_INBOX ? " selected" : "")?>><?php echo $lang_messages['select_inbox'] ?></option>
<option value="-1" <?php echo ($selected == PM_SENTBOX ? " selected" : "")?>><?php echo $lang_messages['select_sentbox'] ?></option>
<?php
foreach ($pmBoxes as $row)
{
$row = (array) $row;
if ($row['boxnumber'] == $selected)
{
echo("<option value=\"" . $row['boxnumber'] . "\" selected>" . $row['name'] . "</option>\n");
}
else
{
echo("<option value=\"" . $row['boxnumber'] . "\">" . $row['name'] . "</option>\n");
}
}
?>
</select> <input class=btn type="submit" value=<?php echo $lang_messages['submit_go'] ?>></form>
<?php
} }
if (!function_exists('messagemenu')) { function messagemenu ($selected = 1) {
$lang_messages = (array) (\App\Support\SupportContext::getGlobal('lang_messages') ?? []);
$BASEURL = \App\Support\SupportContext::getGlobal('BASEURL');
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	print ("<div id=\"pmboxnav\"><ul id=\"pmboxmenu\" class=\"menu\">");
	print ("<li" . ($selected == 1 ? " class=selected" : "") . "><a href=\"" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL . "/messages.php\" >".$lang_messages['text_inbox']."</a></li>");
	print ("<li" . ($selected == -1 ? " class=selected" : "") . "><a href=\"" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL . "/messages.php?action=viewmailbox&box=-1\">".$lang_messages['text_sentbox']."</a></li>");
$pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->orderBy('boxnumber')->get(['boxnumber','name']);
if ($pmBoxes->count())
    foreach ($pmBoxes as $row)
    {
    $row = (array) $row;
    print ("<li" . ($selected == $row['boxnumber'] ? " class=selected" : "") . "><a href=\"" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $BASEURL . "/messages.php?action=viewmailbox&box=".$row['boxnumber']."\">".$row['name']."</a></li>");
    }
	print ("</ul></div>");
} }

// Determine action
$action = \App\Support\SupportContext::getQuery('action') ?? '';
if (!$action)
{
	$action = \App\Support\SupportContext::getPost('action') ?? '';
	if (!$action)
		$action = 'viewmailbox';
}

// View listing of Messages in mail box
if ($action == "viewmailbox")
{
// Get Mailbox Number
$mailbox = \App\Support\SupportContext::getQuery('box') ?? 0;
if (!$mailbox)
	$mailbox = PM_INBOX;

// Get Mailbox Name
if ($mailbox != PM_INBOX && $mailbox != PM_SENTBOX)
{
$pmBoxName = \Nexus\Database\NexusDB::table('pmboxes')
    ->where('userid', $CURUSER['id'])
    ->where('boxnumber', $mailbox)
    ->value('name');
if (!$pmBoxName)
    \App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_invalid_mailbox']);
$mailbox_name = htmlspecialchars($pmBoxName);
}
else
{
if ($mailbox == PM_INBOX)
	$mailbox_name = $lang_messages['text_inbox'];
else
	$mailbox_name = $lang_messages['text_sentbox'];
}

if ($mailbox != PM_SENTBOX)
	$sender_receiver = $lang_messages['text_sender'];
else
	$sender_receiver = $lang_messages['text_receiver'];
// Start Page
?>
<?php messagemenu($mailbox)?>
<table border="0" cellpadding="4" cellspacing="0" width="737">
<tr><td class=colhead align=left><?php echo $lang_messages['col_search_message'] ?></td></tr>
<tr><td class=toolbox align=center><?php echo insertJumpTo($mailbox);?></td></tr>
</table>

<?php
//search
$keyword = trim(\App\Support\SupportContext::getQuery("keyword") ?? '');
$place = \App\Support\SupportContext::getQuery("place") ?? '';
$messageQuery = \App\Models\Message::query();
if ($keyword) {
    switch ($place) {
        case "body":
            $messageQuery->where('msg', 'like', '%'.$keyword.'%');
            break;
        case "title":
            $messageQuery->where('subject', 'like', '%'.$keyword.'%');
            break;
        default:
            $messageQuery->where(function ($q) use ($keyword) {
                $q->where('msg', 'like', '%'.$keyword.'%')
                  ->orWhere('subject', 'like', '%'.$keyword.'%');
            });
    }
}
$unread = \App\Support\SupportContext::getQuery("unread") ?? '';
if ($unread === 'yes' || $unread === 'no') {
    $messageQuery->where('unread', $unread);
}
if ($mailbox != PM_SENTBOX)
{
    $count = (clone $messageQuery)->where('receiver', $CURUSER['id'])->where('location', $mailbox)->count();
}
else
{
    $count = (clone $messageQuery)->where('sender', $CURUSER['id'])->where('saved', 'yes')->count();
}

$perpage = ($CURUSER['pmnum'] ? $CURUSER['pmnum'] : 20);

[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $count, "?action=viewmailbox".($mailbox ? "&box=".$mailbox : "").($place ? "&place=".$place : "").($keyword ? "&keyword=".rawurlencode($keyword) : "").($unread ? "&unread=".$unread : "")."&");

if ($mailbox != PM_SENTBOX)
{
    $messages = (clone $messageQuery)->where('receiver', $CURUSER['id'])->where('location', $mailbox)->orderByDesc('id')->offset($offset)->limit($perpage)->get();
}
else
{
    $messages = (clone $messageQuery)->where('sender', $CURUSER['id'])->where('saved', 'yes')->orderByDesc('id')->offset($offset)->limit($perpage)->get();
}

if ($messages->isEmpty())
{
echo("<p align=\"center\">".$lang_messages['text_no_messages']."</p>\n");
}
else
{
echo $pagertop;
?>
<form action="messages.php" method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="moveordel">
<table border="0" cellpadding="4" cellspacing="0" width="737">
<tr>
<td width="1%" class="colhead" align="center"><?php echo $lang_messages['col_status'] ?></td>
<td class="colhead" align="left"><?php echo $lang_messages['col_subject'] ?> </td>
<?php
print("<td width=\"35%\" class=\"colhead\" align=\"left\">$sender_receiver</td>");
?>
<td width="1%" class="colhead" align="center"><img class="time" src="pic/trans.gif" alt="time" title="<?php echo $lang_messages['col_date'] ?>" /></td>
<td width="1%" class="colhead" align="center"><?php echo $lang_messages['col_act'] ?></td>
</tr>
<?php
foreach ($messages as $message)
{
$row = $message->toArray();
// Get Sender Username
if ($row['sender'] != 0)
{
if ($mailbox != PM_SENTBOX)
	$username = \App\Support\UserDisplay::username($row['sender']);
else
	$username = \App\Support\UserDisplay::username($row['receiver']);
}
else
{
$username = $lang_messages['text_system'];
}
$subject = htmlspecialchars($row['subject']);

if (strlen($subject) <= 0)
{
$subject = $lang_messages['text_no_subject'];
}

if ($row['unread'] == 'yes')
{
echo("<tr>\n<td class=rowfollow align=center><img class=\"unreadpm\" src=\"pic/trans.gif\" alt=\"Unread\" title=".$lang_messages['title_unread']." /></td>\n");
}
else
{
echo("<tr>\n<td class=rowfollow align=center><img class=\"readpm\" src=\"pic/trans.gif\" alt=\"Read\" title=".$lang_messages['title_read']." /></td>\n");
}
echo("<td class=rowfollow align=left><a href=\"messages.php?action=viewmessage&id=" . $row['id'] . "\">" .
$subject . "</a></td>\n");
echo("<td class=rowfollow align=left>$username</td>\n");
echo("<td class=rowfollow nowrap>" . \App\Support\Time::format($row['added'],true,false) . "</td>\n");
echo("<td class=rowfollow><input class=checkbox type=\"checkbox\" name=\"messages[]\" value=\"" . $row['id'] . "\"></td>\n</tr>\n");
}
?>
<tr class="colhead">
<td colspan="5" align="right" class="colhead"><input class=btn type="button" value="<?php echo $lang_messages['input_check_all']; ?>" onClick="this.value=check(form,'<?php echo $lang_messages['input_check_all'] ?>','<?php echo $lang_messages['input_uncheck_all'] ?>')">
<?php if($mailbox != PM_SENTBOX) print("<input class=btn type=\"submit\" name=\"markread\" value=\"".$lang_messages['submit_mark_as_read']."\">") ?>
<input class=btn type="submit" name="delete" value=<?php echo $lang_messages['submit_delete']?>>
<?php
if($mailbox != PM_SENTBOX){
	echo $lang_messages['text_or'];
	print("<input class=btn type=\"submit\" name=\"move\" value=\"".$lang_messages['submit_move_to']."\"> <select name=\"box\"><option value=\"1\">".$lang_messages['text_inbox']."</option>");
        $pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->orderBy('boxnumber')->get(['boxnumber','name']);
        foreach ($pmBoxes as $row)
        {
          $row = (array) $row;
          echo("<option value=\"" . $row['boxnumber'] . "\">" . htmlspecialchars($row['name']) . "</option>\n");
        }
}
?>
      <?php /*
      print("<p align=right><input type=button value=\"Check All\" onClick=\"this.value=check(form)\"><input type=submit value=\"Delete selected\"></p>");
print("</form>");
     */ ?>
        </select>
      </td>
    </tr>

  </form><tr><td class=toolbox colspan=5>
<div align="center"><img class="unreadpm" src="pic/trans.gif" alt="Unread" title="<?php echo $lang_messages['title_unread'] ?>" /><a href="messages.php?action=viewmailbox&box=<?php echo $mailbox?>&unread=yes"><?php echo $lang_messages['text_unread_messages'] ?></a>
<img class="readpm" src="pic/trans.gif" alt="Read" title="<?php echo $lang_messages['title_read'] ?>" /><a href="messages.php?action=viewmailbox&box=<?php echo $mailbox?>&unread=no"><?php echo $lang_messages['text_read_messages'] ?></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="messages.php?action=editmailboxes"><b><?php echo $lang_messages['text_mailbox_manager'] ?></a></b></div></td></tr></table>
<?php
}
}
if ($action == "viewmessage")
{
$pm_id = (int) \App\Support\SupportContext::getQuery('id');
if (!$pm_id)
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_permission']);
}

// Get the message
$message = \App\Models\Message::query()
    ->where('id', $pm_id)
    ->where(function ($q) use ($CURUSER) {
        $q->where('receiver', $CURUSER['id'])
          ->orWhere(function ($sub) use ($CURUSER) {
              $sub->where('sender', $CURUSER['id'])->where('saved', 'yes');
          });
    })
    ->first();
if (!$message) {
    header("Location: messages.php");
    return;
}
$message = $message->toArray();
// Prepare for displaying message
if ($message['sender'] == $CURUSER['id'])
{
// Display to
$sender = \App\Support\UserDisplay::username($message['receiver']);
$reply = "";
$from = $lang_messages['text_to'];
}
else
{
$from = $lang_messages['text_from'];
if ($message['sender'] == 0)
{
$sender = $lang_messages['text_system'];
$reply = "";
}
else
{
$sender = \App\Support\UserDisplay::username($message['sender']);
$reply = " [ <a href=\"sendmessage.php?receiver=" . $message['sender'] . "&replyto=" . $pm_id . "\">".$lang_messages['text_reply']."</a> ]";
}
}
$body = \App\Support\Format::formatComment($message['msg'], true);
$added = $message['added'];
if ($message['sender'] == $CURUSER['id'])
{
$unread = ($message['unread'] == 'yes' ? "<span style=\"color: #FF0000;\"><b>".$lang_messages['text_new']."</b></a>" : "");
}
else
{
$unread = "";
}
$subject = htmlspecialchars($message['subject']);
if (strlen($subject) <= 0)
{
$subject = $lang_messages['text_no_subject'];
}

// Mark message unread
\App\Models\Message::query()->where('id', $pm_id)->where('receiver', $CURUSER['id'])->update(['unread' => 'no']);
$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
// Display message
?>
<h1><?php echo $subject?></h1>
<?php
$mailbox = ($message['sender'] == $CURUSER['id'] ? -1 : $message['location']);
messagemenu($mailbox);
?>
<table width="737" border="0" cellpadding="4" cellspacing="0">
<tr>
<td width="50%" class="colhead" align="left"><?php echo $from?></td>
<td width="50%" class="colhead" align="left"><?php echo $lang_messages['col_date'] ?></td>
</tr>
<tr>
<td class="rowfollow" align="left"><?php echo $sender?></td>
<td class="rowfollow" align="left"><?php echo \App\Support\Time::format($added,true,false)?>&nbsp;&nbsp;<?php echo $unread?></td>
</tr>
<tr>
<td colspan="2" align="left"><?php echo $body?></td>
</tr>
<tr>
<td align=left>
<?php if($message['sender'] != $CURUSER['id']){
print("<form action=\"messages.php\" method=\"post\">" . csrf_field() . "<input type=\"hidden\" name=\"action\" value=\"moveordel\"><input type=\"hidden\" name=\"id\" value=".$pm_id.">
<input type=\"submit\" name=\"move\" value=".$lang_messages['submit_move_to']."><select name=\"box\"><option value=\"1\">".$lang_messages['text_inbox']."</option>");
$pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->orderBy('boxnumber')->get(['boxnumber','name']);
foreach ($pmBoxes as $row)
{
$row = (array) $row;
echo("<option value=\"" . $row['boxnumber'] . "\">" . htmlspecialchars($row['name']) . "</option>\n");
}
print("</select></form>");
}
?>
</td><td align="right" ><font color=white>[ <a href="messages.php?action=deletemessage&id=<?php echo $pm_id?>"><?php echo $lang_messages['text_delete'] ?></a> ]<?php echo $reply?> [ <a

href="messages.php?action=forward&id=<?php echo $pm_id?>"><?php echo $lang_messages['text_forward_pm'] ?></a> ]</font></td>
</tr>
</table>
<?php
}
if ($action == "moveordel")
{
$pm_id = intval(\App\Support\SupportContext::getPost('id') ?? 0);
$pm_box = intval(\App\Support\SupportContext::getPost('box') ?? 0);
$pm_messages = \App\Support\SupportContext::getPost('messages');
if (\App\Support\SupportContext::getPost('markread'))
{
	if ($pm_id)
	{
//Mark a single message as read
$updated = \App\Models\Message::query()->where('id', $pm_id)->where('receiver', $CURUSER['id'])->update(['unread' => 'no']);
	}
	else
	{
        if (empty($pm_messages)) {
            \App\Support\LegacyResponse::abort('Error', $lang_functions['select_at_least_one_record']);
        }
// Mark multiple messages as read
$updated = \App\Models\Message::query()->whereIn('id', $pm_messages)->where('receiver', $CURUSER['id'])->update(['unread' => 'no']);
	}
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
// Check if messages were moved
if ($updated == 0)
	{
	\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_cannot_mark_messages']);
	}

	header("Location: messages.php?action=viewmailbox&box=" . $pm_box);
	return;
}
elseif (\App\Support\SupportContext::getPost('move'))
{
if ($pm_id)
{
// Move a single message
$updated = \App\Models\Message::query()->where('id', $pm_id)->where('receiver', $CURUSER['id'])->update(['location' => $pm_box]);

}
else
{
// Move multiple messages
$updated = \App\Models\Message::query()->whereIn('id', $pm_messages)->where('receiver', $CURUSER['id'])->update(['location' => $pm_box]);
}
// Check if messages were moved
if ($updated == 0)
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_cannot_move_messages']);
}
	$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
	$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
header("Location: messages.php?action=viewmailbox&box=" . $pm_box);
return;
}
elseif (\App\Support\SupportContext::getPost('delete'))
{
if ($pm_id)
{
// Delete a single message
$message = \App\Models\Message::query()->where('id', $pm_id)->first();
if (!$message)
    \App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_cannot_delete_messages']);
$message = $message->toArray();
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
    $deletedCount = \App\Models\Message::query()->where('id', $pm_id)->delete();
    $Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
    $Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
    $deletedCount = \App\Models\Message::query()->where('id', $pm_id)->delete();
    $Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
    $deletedCount = \App\Models\Message::query()->where('id', $pm_id)->update(['location' => 0, 'unread' => 'no']);
    $Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
    $Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
    $deletedCount = \App\Models\Message::query()->where('id', $pm_id)->update(['saved' => 'no']);
    $Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
}
else
{
if (!$pm_messages)
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_message_selected']);
// Delete multiple messages
$deletedCount = 0;
foreach ($pm_messages as $id)
{
$messageRow = \App\Models\Message::query()->where('id', (int)$id)->first();
if (!$messageRow) continue;
$message = $messageRow->toArray();
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
$deletedCount += \App\Models\Message::query()->where('id', (int)$id)->delete();
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
$deletedCount += \App\Models\Message::query()->where('id', (int)$id)->delete();
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
$deletedCount += \App\Models\Message::query()->where('id', (int)$id)->update(['location' => 0, 'unread' => 'no']);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
$deletedCount += \App\Models\Message::query()->where('id', (int)$id)->update(['saved' => 'no']);
}
}
$Cache->delete_value('user_'.$CURUSER['id'].'_unread_message_count');
$Cache->delete_value('user_'.$CURUSER['id'].'_inbox_count');
$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');
}
// Check if messages were moved
if ($deletedCount == 0)
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_cannot_delete_messages']);
}
else
{
header("Location: messages.php?action=viewmailbox");
return;
}
}
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_action']);
}


if ($action == "forward")
{
// Display form
$pm_id = (int) \App\Support\SupportContext::getQuery('id');

// Get the message
$message = \App\Models\Message::query()
    ->where('id', $pm_id)
    ->where(function ($q) use ($CURUSER) {
        $q->where('receiver', $CURUSER['id'])->orWhere('sender', $CURUSER['id']);
    })
    ->first();
if (!$message)
    \App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_permission_forwarding']);
$message = $message->toArray();

// Prepare variables
$subject = "Fwd: " . htmlspecialchars($message['subject']);
$from = $message['receiver'];
$orig = $message['sender'];

$from_name = \App\Support\UserDisplay::username($from);
if ($orig == 0)
{
$orig_name = $orig_name2 = $lang_messages['text_system'];
}
else
{
$orig_name = \App\Support\UserDisplay::username($orig);
$orig_name2 = \App\Models\User::query()->where('id', $orig)->value('username') ?? '';
}

$body = "-------- Original Message from " . $orig_name2 . " --------<br />" . \App\Support\Format::formatComment($message['msg']);

?>
<h1 align="center"><?php echo $lang_messages['text_forward_pm'] ?></h1>
<table border="0" cellpadding="4" cellspacing="0"  width="737">
<form action="takemessage.php" method="post">
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="<?php echo $pm_id?>">
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_to'] ?></td>
<td class="rowfollow" align=left><input type="text" name="to" style="width: 200px"></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_original_receiver'] ?></td>
<td class="rowfollow" align=left><?php echo $from_name?></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_original_sender'] ?></td>
<td class="rowfollow" align=left><?php echo $orig_name?></td>
</tr>
<tr>
<td class="rowhead" align="right"><?php echo $lang_messages['row_subject'] ?></td>
<td class="rowfollow" align=left><input type="text" name="subject" value="<?php echo $subject?>" style="width: 500px"></td>
</tr>
<tr>
<td class="rowhead" align="right" valign="top"><nobr><?php echo $lang_messages['row_message'] ?></nobr></td>
<td class="rowfollow" align=left><textarea name="body" style="width: 500px" rows="8"></textarea><br /><?php echo $body?></td>
</tr>
<tr>
<td class=toolbox colspan="2" align="center"><input class=checkbox type="checkbox" name="save" value="yes"<?php echo $CURUSER['savepms'] == 'yes'?" checked":""?>><?php echo $lang_messages['checkbox_save_message'] ?>&nbsp;
<input type="submit" class="btn" value=<?php echo $lang_messages['submit_forward']?>></td>
</tr>
</table>
</form>
<?php
}
if ($action == "editmailboxes")
{
$pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->orderBy('boxnumber')->get(['id','boxnumber','name']);

?>
<h1><?php echo $lang_messages['text_editing_mailboxes'] ?></h1>
<table width="737" border="0" cellpadding="4" cellspacing="0">
<tr>
<td class="colhead" align="left"><?php echo $lang_messages['text_add_mailboxes'] ?></td>
</tr>
<tr>
<td align=left><?php echo $lang_messages['text_extra_mailboxes_note'] ?><br />
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="add">

<input type="text" name="new1" size="40" maxlength="14"><br />
<input type="text" name="new2" size="40" maxlength="14"><br />
<input type="text" name="new3" size="40" maxlength="14"><br />
<input type="submit" value="<?php echo $lang_messages['submit_add'] ?>">
</form></td>
</tr>
<tr>
<td class="colhead" align=left><?php echo $lang_messages['text_edit_mailboxes'] ?></td>
</tr>
<tr>
<td align=left><?php echo $lang_messages['text_edit_mailboxes_note'] ?>
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="edit">
<?php
$pmBoxesCount = $pmBoxes->count();
if (!$pmBoxesCount)
{
echo ("<span align=\"center\"><b>".$lang_messages['text_no_mailboxes_to_edit']."</b></span>");
}
else
{
foreach ($pmBoxes as $row)
{
$row = (array) $row;
$id = $row['id'];
$name = htmlspecialchars($row['name']);
echo("<input type=\"text\" name=\"edit$id\" value=\"$name\" size=\"40\" maxlength=\"14\"><br />\n");
}
echo("<input type=\"submit\" value=".$lang_messages['submit_edit'].">");
}
?></form></td>
</tr>
</table>
<?php
}
if ($action == "editmailboxes2")
{
$action2 = (string) \App\Support\SupportContext::getQuery('action2');
if (!$action2)
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_action']);
}
if ($action2 == "add")
{
$nameone = \App\Support\SupportContext::getQuery('new1');
$nametwo = \App\Support\SupportContext::getQuery('new2');
$namethree = \App\Support\SupportContext::getQuery('new3');

// Get current max box number
$box = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->max('boxnumber');
$box = (int) $box;
if ($box < 2)
{
$box = 1;
}
if (strlen($nameone) > 0)
{
++$box;
\Nexus\Database\NexusDB::table('pmboxes')->insert(['userid' => $CURUSER['id'], 'name' => $nameone, 'boxnumber' => $box]);
}
if (strlen($nametwo) > 0)
{
++$box;
\Nexus\Database\NexusDB::table('pmboxes')->insert(['userid' => $CURUSER['id'], 'name' => $nametwo, 'boxnumber' => $box]);
}
if (strlen($namethree) > 0)
{
++$box;
\Nexus\Database\NexusDB::table('pmboxes')->insert(['userid' => $CURUSER['id'], 'name' => $namethree, 'boxnumber' => $box]);
}
header("Location: messages.php?action=editmailboxes");
return;
}
if ($action2 == "edit");
{
$pmBoxes = \Nexus\Database\NexusDB::table('pmboxes')->where('userid', $CURUSER['id'])->get(['id','boxnumber','name']);
if ($pmBoxes->isEmpty())
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['text_no_mailboxes_to_edit']);
}
foreach ($pmBoxes as $pmBox)
{
if (((\App\Support\SupportContext::getQuery('edit' . $pmBox->id) !== null)))
{
if (\App\Support\SupportContext::getQuery('edit' . $pmBox->id) != $pmBox->name)
{
if (strlen(\App\Support\SupportContext::getQuery('edit' . $pmBox->id)) > 0)
{
\Nexus\Database\NexusDB::table('pmboxes')->where('id', $pmBox->id)->update(['name' => \App\Support\SupportContext::getQuery('edit' . $pmBox->id)]);
}
else
{
\Nexus\Database\NexusDB::table('pmboxes')->where('id', $pmBox->id)->delete();
\App\Models\Message::query()->where('saved','yes')->where('location', $pmBox->boxnumber)->where('receiver', $CURUSER['id'])->update(['location' => 0]);
\App\Models\Message::query()->where('saved','yes')->where('sender', $CURUSER['id'])->update(['saved' => 'no']);
\App\Models\Message::query()->where('saved','no')->where('location', $pmBox->boxnumber)->where('receiver', $CURUSER['id'])->delete();
\App\Models\Message::query()->where('location', 0)->where('saved','yes')->where('sender', $CURUSER['id'])->delete();
}
}
}
}
header("Location: messages.php?action=editmailboxes");
return;
}
}
if ($action == "deletemessage")
{
$pm_id = (int) \App\Support\SupportContext::getQuery('id');

// Delete message
// Delete message
$message = \App\Models\Message::query()->where('id', $pm_id)->first();
if (!$message)
    \App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_message_id']);
$message = $message->toArray();
if ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'no')
{
    $affected = \App\Models\Message::query()->where('id', $pm_id)->delete();
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] == PM_DELETED)
{
    $affected = \App\Models\Message::query()->where('id', $pm_id)->delete();
}
elseif ($message['receiver'] == $CURUSER['id'] && $message['saved'] == 'yes')
{
    $affected = \App\Models\Message::query()->where('id', $pm_id)->update(['location' => 0]);
}
elseif ($message['sender'] == $CURUSER['id'] && $message['location'] != PM_DELETED)
{
    $affected = \App\Models\Message::query()->where('id', $pm_id)->update(['saved' => 'no']);
}
if ($affected == 0)
{
\App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_could_not_delete_message']);
}
else
{
header("Location: messages.php?action=viewmailbox&id=" . $message['location']);
return;
}
}