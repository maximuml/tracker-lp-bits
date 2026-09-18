@if (! $isAjax)
<html><head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ \App\Support\Style::fontCssUriWithContext() }}" type="text/css">
<link rel="stylesheet" href="{{ \App\Support\Style::cssUriWithContext().'theme.css' }}" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $shoutCsrf }}';</script><script src="js/shoutbox.js" type="text/javascript"></script><link rel="stylesheet" href="styles/shoutbox.css" type="text/css">
{{ \App\Support\Style::addiCodeWithContext() }}
<script type="text/javascript" nonce="{{ $cspNonce ?? '' }}">
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
var SHOUT_LASTID = {{ (int) $lastId }};
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
<body class='inframe'>
@endif
@if (! empty($items))
    @if (! $isAjax)
        <div id="shoutbox-content">
    @endif
    <table data-nx="data" border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>
    @foreach ($items as $item)
        <tr><td class="{{ $item['rowClass'] }}"><span class='date'>[{{ $item['time'] }}]</span> {{ $item['actions'] }} {{ $item['avatarHtml'] }} @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['classBadge'])){{ $item['username'] }} {{ $item['reactions'] }} {{ $item['messageHtml'] }}
</td></tr>
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
