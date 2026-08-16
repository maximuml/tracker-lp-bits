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
$pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) $CURUSER['id']);
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
$pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) $CURUSER['id']);
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
$pmBoxName = \App\Repositories\MessageRepository::getMailboxName((int) $CURUSER['id'], (int) $mailbox);
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
$keyword = trim((string) (\App\Support\SupportContext::getQuery("keyword") ?? ''));
$place = (string) (\App\Support\SupportContext::getQuery("place") ?? '');
$unread = \App\Support\SupportContext::getQuery("unread") ?? '';
$perpage = ($CURUSER['pmnum'] ? (int) $CURUSER['pmnum'] : 20);

$count = \App\Repositories\MessageRepository::getMailboxMessages((int) $CURUSER['id'], (int) $mailbox, $keyword, $place, is_string($unread) ? $unread : null, 0, 0)['count'];

[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $count, "?action=viewmailbox".($mailbox ? "&box=".$mailbox : "").($place ? "&place=".$place : "").($keyword ? "&keyword=".rawurlencode($keyword) : "").($unread ? "&unread=".$unread : "")."&");

$messageResult = \App\Repositories\MessageRepository::getMailboxMessages((int) $CURUSER['id'], (int) $mailbox, $keyword, $place, is_string($unread) ? $unread : null, (int) $offset, (int) $perpage);
$messages = $messageResult['messages'];

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
        $pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) $CURUSER['id']);
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
$messageModel = \App\Repositories\MessageRepository::getMessageForUser($pm_id, (int) $CURUSER['id']);
$message = $messageModel->toArray();
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
\App\Repositories\MessageRepository::markAsRead($pm_id, (int) $CURUSER['id']);
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
$pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) $CURUSER['id']);
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


if ($action == "forward")
{
// Display form
$pm_id = (int) \App\Support\SupportContext::getQuery('id');

// Get the message
$messageModel = \App\Repositories\MessageRepository::getMessageForForward($pm_id, (int) $CURUSER['id']);
if (!$messageModel)
    \App\Support\LegacyResponse::abort($lang_messages['std_error'], $lang_messages['std_no_permission_forwarding']);
$message = $messageModel->toArray();

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
$orig_name2 = \App\Repositories\MessageRepository::getUsername((int) $orig) ?? '';
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
$pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) $CURUSER['id']);

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
