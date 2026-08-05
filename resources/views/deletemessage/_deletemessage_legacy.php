<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
  $id = $_GET["id"];
  if (!is_numeric($id) || $id < 1 || floor($id) != $id)
    die("Invalid ID");

  $type = $_GET["type"];

  if ($type == 'in')
  {
  	// make sure message is in CURUSER's Inbox
	  $msg = \App\Models\Message::query()->where('id', $id)->first(['receiver', 'location']);
	  if (!$msg) die($lang_deletemessage['std_bad_message_id']);
	  $arr = $msg->toArray();
	  if ($arr["receiver"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'in')
	  	\App\Models\Message::query()->where('id', $id)->delete();
    else if ($arr["location"] == 'both')
			\App\Models\Message::query()->where('id', $id)->update(['location' => 'out']);
    else
    	die($lang_deletemessage['std_not_in_inbox']);
  }
	elseif ($type == 'out')
  {
   	// make sure message is in CURUSER's Sentbox
	  $msg = \App\Models\Message::query()->where('id', $id)->first(['sender', 'location']);
	  if (!$msg) die($lang_deletemessage['std_bad_message_id']);
	  $arr = $msg->toArray();
	  if ($arr["sender"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'out')
	  	\App\Models\Message::query()->where('id', $id)->delete();
    else if ($arr["location"] == 'both')
			\App\Models\Message::query()->where('id', $id)->update(['location' => 'in']);
    else
    	die($lang_deletemessage['std_not_in_sentbox']);
  }
  else
  	die($lang_deletemessage['std_unknown_pm_type']);
  header("Location: " . get_protocol_prefix() . "$BASEURL/messages.php".($type == 'out'?"?out=1":""));
  return;
?>
