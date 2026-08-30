@php
$lang_shoutbox = (array) (\app(\App\Support\Globals::class)->get('lang_shoutbox') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$isAjax = (bool) ($isAjax ?? ! empty(\request()->query('ajax')));
$where = (string) ($where ?? 'shoutbox');
$refresh = (int) ($refresh ?? ($CURUSER['sbrefresh'] ?? 120));
$lastId = (int) ($lastId ?? 0);
$rows = $rows ?? collect();
$currentUserId = (int) ($currentUserId ?? (int) ($CURUSER['id'] ?? 0));
$isStaff = (bool) ($isStaff ?? false);
$reactionData = (array) ($reactionData ?? ['counts' => [], 'mine' => [], 'users' => []]);
@endphp
@if (! $isAjax)
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ \App\Support\Style::fontCssUriWithContext() }}" type="text/css">
<link rel="stylesheet" href="{{ \App\Support\Style::cssUriWithContext().'theme.css' }}" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><script>var SHOUT_CSRF = '{{ htmlspecialchars(\App\Support\Shoutbox::csrfToken((int) ($CURUSER['id'] ?? 0))) }}';</script><script src="js/shoutbox.js" type="text/javascript"></script><link rel="stylesheet" href="styles/shoutbox.css" type="text/css">
{!! \App\Support\Style::addiCodeWithContext() !!}
@php
    $startcountdown = 'startcountdown('.$refresh.');shoutboxInitSSE('.htmlspecialchars(json_encode($where, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').','.$lastId.');shoutAttachToggleHandler();';
@endphp
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
var SHOUT_REFRESH = {{ (int) $refresh }};
var SHOUT_TYPE = @json($where, JSON_UNESCAPED_UNICODE);
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
<body class='inframe' onload="{!! $startcountdown !!}">
@endif
@php
    $reactionCounts = $reactionData['counts'] ?? [];
    $reactionMine = $reactionData['mine'] ?? [];
    $reactionUsers = $reactionData['users'] ?? [];
@endphp
@if ($rows->isEmpty())
@else
    @php
        $rows = $rows->map(fn ($r) => (array) $r);
        $showAvatars = (isset($CURUSER['avatars'])) && $CURUSER['avatars'] === 'yes';
        $tooltipAvatar = $lang_shoutbox['tooltip_avatar'] ?? 'Open profile';
        $tooltipReply = $lang_shoutbox['tooltip_nick_reply'] ?? 'Reply via @';
        $labelMore = $lang_shoutbox['shout_show_more'] ?? 'more';
        $labelLess = $lang_shoutbox['shout_show_less'] ?? 'less';
        $groupWindowSec = 120;
        $prevUserId = 0;
        $prevDate = 0;
    @endphp
    @if (! $isAjax)
        <div id="shoutbox-content">
    @endif
    <table border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>
    @foreach ($rows as $arr)
        @php
            $arr = (array) $arr;
            $currUserId = (int) $arr['userid'];
            $currDate = (int) $arr['date'];
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
            if (! empty($arr['edited_at']) && (int) $arr['edited_at'] > 0) {
                $editedNote = ' <span class="shout-edited-note">('.htmlspecialchars((string) ($lang_shoutbox['text_edited'] ?? 'edited')).' '.\App\Support\Shoutbox::formatTime((int) $arr['edited_at'], true).')</span>';
            }
            $avatarUrl = 'pic/default_avatar.png';
            $nickReplyName = '';
            if ($arr['userid']) {
                $username = \App\Support\UserDisplay::username($arr['userid'], false, true, true, true, false, false, '', true);
                $userRow = \App\Support\UserDisplay::row((int) $arr['userid']);
                $nickReplyName = trim((string) ($userRow['username'] ?? ''));
                $classBadge = \App\Support\Shoutbox::classBadge((int) ($userRow['class'] ?? 0));
                if ($showAvatars) {
                    $rawAvatar = trim((string) ($userRow['avatar'] ?? ''));
                    if ($rawAvatar !== '') {
                        $avatarUrl = $rawAvatar;
                    }
                }
                if ($nickReplyName !== '' && (int) ($CURUSER['id'] ?? 0) > 0) {
                    $onclickAttr = 'return shoutReply('.htmlspecialchars(json_encode($nickReplyName, JSON_UNESCAPED_UNICODE), ENT_QUOTES).')';
                    $username = preg_replace(
                        '#href="[^"]*userdetails\.php\?id=\d+"#',
                        'href="javascript:void(0)" onclick="'.$onclickAttr.'" title="'.htmlspecialchars($tooltipReply, ENT_QUOTES).'"',
                        $username,
                        1
                    );
                    $username = preg_replace(
                        '#<a\s([^>]*onclick="return shoutReply\()#',
                        '<a class="shout-nick-reply" $1',
                        $username,
                        1
                    );
                }
            } else {
                $username = $lang_shoutbox['text_guest'] ?? '';
                $classBadge = '';
            }
            $avatarImg = '<img class="shout-avatar" src="'.htmlspecialchars($avatarUrl).'" alt="" onerror="this.onerror=null;this.src=\'pic/default_avatar.png\';" />';
            if ($currUserId > 0) {
                $avatarHtml = '<a class="shout-avatar-link" href="userdetails.php?id='.$currUserId.'" target="_blank" title="'.htmlspecialchars($tooltipAvatar, ENT_QUOTES).'">'.$avatarImg.'</a>';
            } else {
                $avatarHtml = $avatarImg;
            }
            $time = \App\Support\Shoutbox::formatTime($currDate, true);
            $mentionsMe = false;
            $message = \App\Support\Shoutbox::formatMessage($arr['text'], $currentUserId, $mentionsMe);
            $plainLen = mb_strlen(strip_tags($message));
            $isLong = $plainLen > 280;
            $msgClass = $isLong ? 'shout-msg shout-msg-clamped' : 'shout-msg';
            $messageHtml = '<span id="shout-msg-'.$arr['id'].'" class="'.$msgClass.'" data-raw="'.htmlspecialchars((string) $arr['text'], ENT_QUOTES).'">'.$message.'</span>';
            if ($isLong) {
                $messageHtml .= '<a class="shout-msg-toggle" href="javascript:void(0)" data-on="'.htmlspecialchars($labelLess, ENT_QUOTES).'" data-off="'.htmlspecialchars($labelMore, ENT_QUOTES).'">'.htmlspecialchars($labelMore).'</a>';
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
        @endphp
        <tr><td class="{{ $rowClass }}"><span class='date'>[{{ $time }}]</span> {!! $actions !!} {!! $avatarHtml !!} {!! $classBadge !!}{!! $username !!} {!! $reactions !!} {!! $messageHtml !!}
</td></tr>
        @php
            $prevUserId = $currUserId;
            $prevDate = $currDate;
        @endphp
    @endforeach
    </table>
    @if (! $isAjax)
        </div>
    @endif
@endif
@if (! $isAjax)
</body>
</html>
@endif
