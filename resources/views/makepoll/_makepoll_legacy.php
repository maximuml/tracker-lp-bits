<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$action = $_GET["action"] ?? '';
$pollid = intval($_GET["pollid"] ?? 0);
$poll = [];

if ($action == "edit")
{
	int_check($pollid,true);
	$poll = (array) \Nexus\Database\NexusDB::table('polls')->where('id', $pollid)->first();
	if (!$poll)
		stderr($lang_makepoll['std_error'], $lang_makepoll['std_no_poll_id']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	$pollid = intval($_POST["pollid"] ?? 0);
	$question = htmlspecialchars($_POST["question"]);
	$returnto = htmlspecialchars($_POST["returnto"]);
	$options = [];
	for ($i = 0; $i <= 19; $i++) {
	    $key = "option$i";
	    $options[$key] = htmlspecialchars($_POST[$key] ?? '');
	}

	if (!$question || !$options['option0'] || !$options['option1'])
		stderr($lang_makepoll['std_error'], $lang_makepoll['std_missing_form_data']);

	$data = array_merge(['question' => $question], $options);
	if ($pollid) {
	    \Nexus\Database\NexusDB::table('polls')->where('id', $pollid)->update($data);
	} else {
	    $data['added'] = date("Y-m-d H:i:s");
	    \Nexus\Database\NexusDB::table('polls')->insert($data);
	}

	$Cache->delete_value('current_poll_content');
	$Cache->delete_value('current_poll_result', true);
  	if ($returnto == "main")
		header("Location: " . get_protocol_prefix() . "$BASEURL");
  	elseif ($pollid)
		header("Location: " . get_protocol_prefix() . "$BASEURL/log.php?action=poll#$pollid");
	else
		header("Location: " . get_protocol_prefix() . "$BASEURL");
	die;
}

if ($pollid){
	stdhead($lang_makepoll['head_edit_poll']);
	print("<h1>".$lang_makepoll['text_edit_poll']."</h1>");
}
else
{
	stdhead($lang_makepoll['head_new_poll']);
	// Warn if current poll is less than 3 days old
	$lastPoll = (array) \Nexus\Database\NexusDB::table('polls')->orderByDesc('added')->first(['question', 'added']);
	if ($lastPoll)
	{
		$hours = floor((strtotime(date("Y-m-d H:i:s")) - strtotime($lastPoll["added"])) / 3600);
		$days = floor($hours / 24);
		if ($days < 3)
		{
			if ($days >= 1)
				$t = $days.$lang_makepoll['text_day'] . add_s($days);
			else
				$t = $hours.$lang_makepoll['text_hour'] . add_s($hours);
			print("<p><font class=striking><b>".$lang_makepoll['text_current_poll']."(<i>" . $lastPoll["question"] . "</i>)".$lang_makepoll['text_is_only'].$t.$lang_makepoll['text_old']."</b></font></p>");
		}
	}
	print("<h1>".$lang_makepoll['text_make_poll']."</h1>");
}
?>

<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=makepoll.php>
<style type="text/css">
input.mp
{
	width: 450px;
}
</style>
<tr><td class=rowhead><?php echo $lang_makepoll['text_question']?> <font color=red>*</font></td><td align=left><input name=question class=mp maxlength=255 value="<?php echo $poll['question'] ?? ''?>"></td></tr>
<?php for ($i = 0; $i <= 19; $i++) { ?>
<tr><td class=rowhead><?php echo $lang_makepoll['text_option']?><?php echo $i + 1?><?php echo $i < 2 ? ' <font color=red>*</font>' : ''?></td><td align=left><input name=option<?php echo $i?> class=mp maxlength=40 value="<?php echo $poll["option$i"] ?? ''?>"><br /></td></tr>
<?php } ?>
<tr><td colspan=2 align=center><input type=submit value="<?php echo $pollid ? $lang_makepoll['submit_edit_poll'] : $lang_makepoll['submit_create_poll']?>" style='height: 20pt'></td></tr>
</table>
<p><font color=red>*</font><?php echo $lang_makepoll['text_required']?></p>
<?php
if ($pollid)
print("<input type=hidden name=pollid value=\"".$poll["id"]."\">");
?>
<input type=hidden name=returnto value="<?php echo htmlspecialchars($_GET["returnto"] ?? '') ? htmlspecialchars($_GET["returnto"] ?? '') : htmlspecialchars($_SERVER["HTTP_REFERER"] ?? '')?>">
</form>

<?php
stdfoot();
?>
