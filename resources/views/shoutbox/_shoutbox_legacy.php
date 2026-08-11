<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (((\App\Support\SupportContext::getQuery('del') !== null)))
{
	if (\App\Support\Validators::isId(\App\Support\SupportContext::getQuery('del')))
	{
		if(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE))
		{
			$delId = (int)\App\Support\SupportContext::getQuery('del');
			\Nexus\Database\NexusDB::table('shoutbox')->where('id', $delId)->delete();
			\Nexus\Database\NexusDB::table('shoutbox_reactions')->where('shoutbox_id', $delId)->delete();
		}
	}
}
$isAjax = !empty(\App\Support\SupportContext::getQuery('ajax'));
$where = 'shoutbox';
$refresh = ($CURUSER['sbrefresh'] ?? 120);
if (!$isAjax):
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri()."theme.css"?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><script>var SHOUT_CSRF = '<?php echo htmlspecialchars(\App\Support\Shoutbox::csrfToken((int)($CURUSER['id'] ?? 0))); ?>';</script><script src="js/shoutbox.js" type="text/javascript"></script><link rel="stylesheet" href="styles/shoutbox.css" type="text/css">
<?php
print(get_style_addicode());
$lastIdQuery = \Nexus\Database\NexusDB::table('shoutbox');
\App\Support\Shoutbox::applyTypeFilter($lastIdQuery, $where, $CURUSER ?? null);
$lastId = (int)$lastIdQuery->max('id');
$startcountdown = "startcountdown(".$refresh.");shoutboxInitSSE(" . htmlspecialchars(json_encode($where, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . "," . $lastId . ");shoutAttachToggleHandler();";
?>
<script type="text/javascript">
//<![CDATA[
var t;
function startcountdown(time)
{
if (t) { clearTimeout(t); t = null; }
if (parent && parent.document && parent.document.getElementById('countdown')) {
parent.document.getElementById('countdown').innerHTML=time;
}
if (time > 0) {
time=time-1;
t=setTimeout(function(){ startcountdown(time); },1000);
}
}
function shoutReply(nick) {
	try {
		var input = null;
		if (parent && parent.document && parent.document.forms && parent.document.forms['shbox'] && parent.document.forms['shbox'].shbox_text) {
			input = parent.document.forms['shbox'].shbox_text;
		}
		if (!input) { return false; }
		var prefix = '@' + nick + ', ';
		var val = input.value || '';
		if (val.indexOf(prefix) !== 0) {
			input.value = prefix + val;
		}
		input.focus();
		try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
	} catch (e) {}
	return false;
}
// Live polling: replaces the legacy <meta refresh> reload that used to wipe
// scroll position and any expanded long-message state every $refresh seconds.
// We re-fetch the rendered table via ?ajax=1 and swap the inner HTML of the
// content wrapper. The countdown timer is restarted on every successful poll.
var SHOUT_REFRESH = <?php echo (int)$refresh; ?>;
var SHOUT_TYPE = <?php echo json_encode($where, JSON_UNESCAPED_UNICODE); ?>;
var pollTimer = null;
function schedulePoll() {
	if (pollTimer) { clearTimeout(pollTimer); }
	if (SHOUT_REFRESH <= 0) { return; }
	pollTimer = setTimeout(shoutPoll, SHOUT_REFRESH * 1000);
}
function shoutPoll() {
	var url = 'shoutbox.php?type=' + encodeURIComponent(SHOUT_TYPE) + '&ajax=1&_=' + Date.now();
	try {
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState !== 4) { return; }
			var c = document.getElementById('shoutbox-content');
			if (xhr.status >= 200 && xhr.status < 300 && c) {
				c.innerHTML = xhr.responseText;
			}
			try { startcountdown(SHOUT_REFRESH); } catch (e) {}
			schedulePoll();
		};
		xhr.send();
	} catch (e) {
		schedulePoll();
	}
}
// One-time delegated handler so the [more]/[less] toggle keeps working after
// the table is replaced by a poll.
function shoutAttachToggleHandler() {
	var host = document.getElementById('shoutbox-content');
	if (!host || host.__shoutToggleBound) { return; }
	host.__shoutToggleBound = true;
	host.addEventListener('click', function(e) {
		var btn = e.target;
		while (btn && btn !== host && !(btn.classList && btn.classList.contains('shout-msg-toggle'))) {
			btn = btn.parentNode;
		}
		if (!btn || btn === host) { return; }
		var msg = btn.previousSibling;
		while (msg && msg.nodeType === 3) { msg = msg.previousSibling; }
		if (!msg) { return; }
		var clamped = msg.classList.toggle('shout-msg-clamped');
		btn.textContent = clamped ? btn.getAttribute('data-off') : btn.getAttribute('data-on');
		if (e.preventDefault) { e.preventDefault(); }
	}, false);
}
//]]>
</script>
</head>
<body class='inframe' onload="<?php echo $startcountdown?>">
<?php
endif; // if (!$isAjax)
?>
<?php
if (((\App\Support\SupportContext::getQuery('sent') !== null)) && \App\Support\SupportContext::getQuery('sent') === 'yes' && !empty(\App\Support\SupportContext::getQuery('shbox_text'))) {
    $userid = (int) ($CURUSER['id'] ?? 0);
    if (!$userid) {
        do_log('Someone is hacking shoutbox. no_permission_to_shoutbox - IP : ' . \App\Support\Network::clientIp());
        echo $lang_shoutbox['text_no_permission_to_shoutbox']; return;
    }
    $type = 'sb';
    $date = time();
    $text = trim(\App\Support\SupportContext::getQuery('shbox_text'));
    if (mb_strlen($text) > \App\Support\Shoutbox::MAX_MESSAGE_LENGTH) {
        echo ($lang_shoutbox['text_message_too_long'] ?? 'Message too long'); return;
    }
    $lock = new \Nexus\Database\NexusLock("shoutbox:$userid", 60);
    if (! $lock->acquire()) {
        echo $lang_shoutbox['speaking_too_often']; return;
    }
    \Nexus\Database\NexusDB::table('shoutbox')->insert([
        'userid' => $userid,
        'date' => $date,
        'text' => $text,
        'type' => $type,
    ]);
    print "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
}

if (!(isset($CURUSER))) {
    echo '<h1>' . $lang_shoutbox['std_access_denied'] . '</h1><p>' . $lang_shoutbox['std_access_denied_note'] . '</p></body></html>'; return;
}

$limit = ($CURUSER['sbnum'] ?? 70);
$query = \Nexus\Database\NexusDB::table('shoutbox')->orderByDesc('date')->limit($limit);
\App\Support\Shoutbox::applyTypeFilter($query, $where, $CURUSER ?? null);
/**
 * Build a small role badge for staff/VIP-tier classes. Returns empty string for
 * regular users so the shoutbox doesn't get cluttered with badges on every row.
 */
function shoutbox_class_badge($class)
{
	static $map = null;
	if ($map === null) {
		$map = [
			UC_VIP           => ['VIP',  '#9c27b0'],
			UC_RETIREE       => ['RET',  '#607d8b'],
			UC_UPLOADER      => ['UPL',  '#1976d2'],
			UC_MODERATOR     => ['MOD',  '#388e3c'],
			UC_ADMINISTRATOR => ['ADM',  '#d32f2f'],
			UC_SYSOP         => ['SYS',  '#b71c1c'],
			UC_STAFFLEADER   => ['CHIEF','#e65100'],
		];
	}
	$class = (int)$class;
	if (!(isset($map[$class]))) {
		return '';
	}
	$label = $map[$class][0];
	$color = $map[$class][1];
	$tooltip = '';
	if (function_exists('get_user_class_name')) {
		$tooltip = (string)\App\Support\UserClass::name($class, false, false, true);
	}
	return '<span class="shout-class-badge" style="background:' . $color . '" title="' . htmlspecialchars($tooltip, ENT_QUOTES) . '">' . $label . '</span>';
}

$rows = $query->get();
if ($rows->isEmpty())
print("\n");
else
{
	$rows = $rows->map(fn ($r) => (array) $r);
	$showAvatars = (isset($CURUSER['avatars'])) && $CURUSER['avatars'] === 'yes';
	$tooltipAvatar = $lang_shoutbox['tooltip_avatar'] ?? 'Open profile';
	$tooltipReply = $lang_shoutbox['tooltip_nick_reply'] ?? 'Reply via @';
	$labelMore = $lang_shoutbox['shout_show_more'] ?? 'more';
	$labelLess = $lang_shoutbox['shout_show_less'] ?? 'less';
	// Group consecutive shouts from the same user posted within this many seconds.
	// Avatar/nick are hidden on the second+ rows of the group; rows render as a
	// continuation with a 22px spacer so message text stays aligned.
	$groupWindowSec = 120;
	$prevUserId = 0;
	$prevDate = 0;
	$currentUserId = (int)($CURUSER['id'] ?? 0);
	$isStaff = \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::SB_MANAGE);

	$shoutIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
	$reactionData = \App\Support\Shoutbox::prefetchReactions($shoutIds, $currentUserId);
	$reactionCounts = $reactionData['counts'];
	$reactionMine = $reactionData['mine'];
	$reactionUsers = $reactionData['users'];

	if (!$isAjax) {
		print('<div id="shoutbox-content">');
	}
	print("<table border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>\n");

	foreach ($rows as $arr)
	{
		$arr = (array) $arr;
		$currUserId = (int)$arr["userid"];
		$currDate = (int)$arr["date"];
		// Iteration is DESC (newest first), so the previous row is newer in time.
		// Treat the current row as a continuation of an above group when it shares
		// userid with the row above and is within the group window.
		$isContinuation = (
			$currUserId > 0
			&& $currUserId === $prevUserId
			&& $prevDate > 0
			&& abs($prevDate - $currDate) <= $groupWindowSec
		);
		$actions = \App\Support\Shoutbox::renderActions($arr, $currentUserId, $isStaff);
		$shoutId = (int) $arr['id'];
		$reactions = \App\Support\Shoutbox::renderReactions(
			$shoutId,
			$currentUserId,
			$reactionCounts[$shoutId] ?? [],
			$reactionMine[$shoutId] ?? [],
			$reactionUsers[$shoutId] ?? []
		);
		$editedNote = '';
		if (!empty($arr['edited_at']) && (int)$arr['edited_at'] > 0) {
			$editedNote = ' <span class="shout-edited-note">(' . htmlspecialchars((string) ($lang_shoutbox['text_edited'] ?? 'edited')) . ' ' . \App\Support\Shoutbox::formatTime((int)$arr['edited_at'], true) . ')</span>';
		}
		$avatarUrl = 'pic/default_avatar.png';
		$nickReplyName = '';
		if ($arr["userid"]) {
			$username = \App\Support\UserDisplay::username($arr["userid"],false,true,true,true,false,false,"",true);
			$userRow = \App\Support\UserDisplay::row((int)$arr["userid"]);
			$nickReplyName = trim((string)($userRow["username"] ?? ''));
			$classBadge = shoutbox_class_badge((int)($userRow["class"] ?? 0));
			if ($showAvatars) {
				$rawAvatar = trim((string)($userRow["avatar"] ?? ''));
				if ($rawAvatar !== '') {
					$avatarUrl = $rawAvatar;
				}
			}
			// Repurpose the nickname link: instead of going to userdetails, clicking the nick
			// inserts "@nick, " into the input box. Avatar takes over the profile-link role below.
			// get_username() emits an absolute URL (scheme+host), so the regex must be tolerant
			// of any prefix between `href="` and `userdetails.php`.
			if ($nickReplyName !== '' && (int)($CURUSER['id'] ?? 0) > 0) {
				$onclickAttr = 'return shoutReply(' . htmlspecialchars(json_encode($nickReplyName, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')';
				$username = preg_replace(
					'#href="[^"]*userdetails\.php\?id=\d+"#',
					'href="javascript:void(0)" onclick="' . $onclickAttr . '" title="' . htmlspecialchars($tooltipReply, ENT_QUOTES) . '"',
					$username,
					1
				);
				// Tag the rewritten link so we can give it a pointer cursor without affecting other anchors.
				$username = preg_replace(
					'#<a\s([^>]*onclick="return shoutReply\()#',
					'<a class="shout-nick-reply" $1',
					$username,
					1
				);
			}
		} else {
			$username = $lang_shoutbox['text_guest'];
			$classBadge = '';
		}
		$avatarImg = '<img class="shout-avatar" src="' . htmlspecialchars($avatarUrl) . '" alt="" onerror="this.onerror=null;this.src=\'pic/default_avatar.png\';" />';
		if ($currUserId > 0) {
			$avatarHtml = '<a class="shout-avatar-link" href="userdetails.php?id=' . $currUserId . '" target="_blank" title="' . htmlspecialchars($tooltipAvatar, ENT_QUOTES) . '">' . $avatarImg . '</a>';
		} else {
			$avatarHtml = $avatarImg;
		}
		$time = \App\Support\Shoutbox::formatTime($currDate, true);
		$mentionsMe = false;
		$message = \App\Support\Shoutbox::formatMessage($arr["text"], $currentUserId, $mentionsMe);
		$plainLen = mb_strlen(strip_tags($message));
		$isLong = $plainLen > 280;
		$msgClass = $isLong ? 'shout-msg shout-msg-clamped' : 'shout-msg';
		$messageHtml = '<span id="shout-msg-' . $arr['id'] . '" class="' . $msgClass . '" data-raw="' . htmlspecialchars((string) $arr['text'], ENT_QUOTES) . '">' . $message . '</span>';
		if ($isLong) {
			$messageHtml .= '<a class="shout-msg-toggle" href="javascript:void(0)" data-on="' . htmlspecialchars($labelLess, ENT_QUOTES) . '" data-off="' . htmlspecialchars($labelMore, ENT_QUOTES) . '">' . htmlspecialchars($labelMore) . '</a>';
		}
		$messageHtml .= $editedNote;
		$rowClasses = ['shoutrow'];
		if ($mentionsMe) {
			$rowClasses[] = 'shoutrow-mentions-me';
		}
		if ($isContinuation) {
			$rowClasses[] = 'shout-row-grouped';
			$avatarHtml = '<span class="shout-avatar-spacer" aria-hidden="true"></span>';
			$username = '';
			$classBadge = '';
		}
		$rowClass = implode(' ', $rowClasses);
		print("<tr><td class=\"".$rowClass."\"><span class='date'>[".$time."]</span> ".
$actions ." ". $avatarHtml . " " . $classBadge . $username." " . $reactions . " " . $messageHtml."
</td></tr>\n");
		$prevUserId = $currUserId;
		$prevDate = $currDate;
	}
	print("</table>");
	if (!$isAjax) {
		print('</div>');
	}
}
if (!$isAjax):
?>
</body>
</html>
<?php
endif;
