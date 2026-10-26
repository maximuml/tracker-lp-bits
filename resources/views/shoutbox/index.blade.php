@if (! $isAjax)
<html data-theme="{{ $theme }}" data-fontsize="{{ $fontSize }}"><head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc(\App\Support\Style::cssUriWithContext().'theme.css') }}" type="text/css">
<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc('css/nxt.css') }}" type="text/css">
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $shoutCsrf }}';window.SHOUT_LANG = {{ Illuminate\Support\Js::from([
    'requestFailed' => __('shoutbox.js_request_failed'),
    'invalidResponse' => __('shoutbox.js_invalid_response'),
    'spoilerTitle' => __('shoutbox.js_spoiler_title'),
    'quoteAuthor' => __('shoutbox.js_quote_author'),
    'url' => __('shoutbox.js_url'),
    'linkText' => __('shoutbox.js_link_text'),
    'confirmDelete' => __('shoutbox.js_confirm_delete'),
    'collapse' => __('shoutbox.js_collapse'),
    'expand' => __('shoutbox.js_expand'),
    'newMentions' => __('shoutbox.js_new_mentions'),
]) }};</script><script src="{{ \App\Support\AssetAppender::versionedSrc('js/shoutbox.js') }}" type="text/javascript"></script><link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc('styles/shoutbox.css') }}" type="text/css"><link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc('css/modern.css') }}" type="text/css">
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
	var url = '/web/shoutbox?type=' + encodeURIComponent(SHOUT_TYPE) + '&ajax=1&_=' + Date.now();
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
    <x-data-table :caption="__('index.text_shoutbox')" captionHidden>
    @foreach ($items as $item)
        <tr><td class="{{ $item['rowClass'] }}"><span class='date'>[{{ $item['time'] }}]</span> @if ($item['actions'] !== null)<x-shoutbox.actions :msg-id="$item['actions']['msgId']" :can-edit="$item['actions']['canEdit']" :can-delete="$item['actions']['canDelete']" />@endif <x-shoutbox.avatar :url="$item['avatarUrl']" :user-id="$item['avatarUserId']" :tooltip="$item['avatarTooltip']" :spacer="$item['avatarSpacer']" /> @if ($item['classBadge'] !== null)<x-shoutbox.class-badge :modifier="$item['classBadge']['modifier']" :tooltip="$item['classBadge']['tooltip']" :label="$item['classBadge']['label']" />@endif@if (! empty($item['isGuest']))<b>{{ $item['username'] }}</b>@else{{ $item['username'] }}@endif @if ($item['reactions'] !== null)<x-shoutbox.reactions :shout-id="$item['reactions']['shoutId']" :reactions="$item['reactions']['reactions']" :show-picker="$item['reactions']['showPicker']" :picker-emojis="$item['reactions']['pickerEmojis']" />@endif <x-shoutbox.message :id="$item['msgId']" :is-long="$item['msgLong']" :raw="$item['msgRaw']" :formatted="$item['msgFormatted']" :edited-time="$item['editedTime']" :label-more="$item['labelMore']" :label-less="$item['labelLess']" />
</td></tr>
    @endforeach
    </x-data-table>
    @if (! $isAjax)
        </div>
    @endif
@endif
@if (! $isAjax)
</body>
</html>
@endif
