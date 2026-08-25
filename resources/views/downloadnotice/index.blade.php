@php
$lang_downloadnotice = (array) (\App\Support\SupportContext::getGlobal('lang_downloadnotice') ?? []);
$CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$torrentid = (int) ($torrentid ?? 0);
$type = (string) ($type ?? 'firsttime');
$title = $title ?? ($lang_downloadnotice['head_download_notice'] ?? 'Download Notice');
$note = (string) ($note ?? '');
$noticenexttime = (string) ($noticenexttime ?? '');
$showrationotice = (bool) ($showrationotice ?? false);
$showclientnotice = (bool) ($showclientnotice ?? false);
$forcecheck = (bool) ($forcecheck ?? false);
$tdattr = (string) ($tdattr ?? '');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h2>{{ $title }}</h2>
<table width="100%"><tr>
<td colspan="2" class="text" align="left"><p>{{ $note }}</p></td></tr>
<tr>
@if (! empty($showrationotice))
<td class="text" align="left" valign="top" {{ $tdattr }}>
<h3>{{ $lang_downloadnotice['text_this_is_private_tracker'] ?? '' }}</h3>
<p>{{ $lang_downloadnotice['text_private_tracker_note_one'] ?? '' }}<i>({{ $lang_downloadnotice['text_learn_more'] ?? '' }}<a class="faqlink" href="{{ NEXUSWIKIURL ?? '' }}/Private Tracker" target="_blank">{{ $lang_downloadnotice['text_nexuswiki'] ?? '' }}</a>)</i></p>
<p>{{ $lang_downloadnotice['text_private_tracker_note_two'] ?? '' }}<i>({{ $lang_downloadnotice['text_see_ratio'] ?? '' }}<a class="faqlink" href="faq.php#id23" target="_blank">{{ $lang_downloadnotice['text_faq'] ?? '' }}</a>)</i></p>
<p>{{ $lang_downloadnotice['text_private_tracker_note_three'] ?? '' }}</p>
<img src="pic/ratio.png" alt="ratio" />
<p>{{ $lang_downloadnotice['text_private_tracker_note_four'] ?? '' }}</p>
</td>
@endif
@if (! empty($showclientnotice))
<td class="text" align="left" valign="top" {{ $tdattr }}>
<h3>{{ $lang_downloadnotice['text_use_allowed_clients'] ?? '' }}</h3>
<p>{{ $lang_downloadnotice['text_allowed_clients_note_one'] ?? '' }}</p>
<p>{{ $lang_downloadnotice['text_allowed_clients_note_two'] ?? '' }}<a class='faqlink' href='faq.php#id29' target='_blank'>{{ $lang_downloadnotice['text_faq'] ?? '' }}</a>{{ $lang_downloadnotice['text_allowed_clients_note_three'] ?? '' }}</p>
<table width="100%">
<tr>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="https://www.qbittorrent.org/download" target="_blank" title="{{ $lang_downloadnotice['title_download'] ?? '' }}qBittorrent"><img src="pic/qbittorrent.png" alt="qBittorrent"  width="128" height="128" /></a>
</td>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="https://transmissionbt.com/download/" target="_blank" title="{{ $lang_downloadnotice['title_download'] ?? '' }}Transmission"><img src="pic/transmission.png" alt="Transmission"  width="128" height="128" /></a>
</td>
</tr>
<tr>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="https://www.qbittorrent.org/download" target="_blank" title="{{ $lang_downloadnotice['title_download'] ?? '' }}qBittorrent"><b>qBittorrent</b></a></div>
<div>{{ $lang_downloadnotice['text_for'] ?? '' }}Windows, Linux, Mac OS</div>
</td>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="https://transmissionbt.com/download/" target="_blank" title="{{ $lang_downloadnotice['title_download'] ?? '' }}Transmission"><b>Transmission</b></a></div>
<div>{{ $lang_downloadnotice['text_for'] ?? '' }}Windows, Linux, Mac OS</div>
</td>
</tr>
</table>
</td>
@endif
</tr>
@if (! empty($torrentid))
<tr>
<td class="text" colspan="2">
<form action="?" method="post"><p>{{ $lang_downloadnotice['text_for_more_information_read'] ?? '' }}<a class="faqlink" href="rules.php" target="_blank">{{ $lang_downloadnotice['text_rules'] ?? '' }}</a>{{ $lang_downloadnotice['text_and'] ?? '' }}<a class="faqlink" href="faq.php" target="_blank">{{ $lang_downloadnotice['text_faq'] ?? '' }}</a><br />
<input type="hidden" name="id" value="{{ $torrentid }}" />
<input type="hidden" name="type" value="{{ htmlspecialchars((string) $type) }}" />
<input type="checkbox" name="hidenotice" id="hidenotice" value="1"@if (! empty($forcecheck)) disabled="disabled"@else checked="checked"@endif /><label for="hidenotice">{{ $noticenexttime }}</label>
@if (! empty($forcecheck))
<br /><input type="checkbox" name="letmedown" id="letmedown" value="{{ htmlspecialchars((string) $type) }}" onclick="if (this.checked) {document.getElementById('continuedownload').disabled = false;}else{document.getElementById('continuedownload').disabled = true;}" /><label for="letmedown"><span class="big">{{ $lang_downloadnotice['text_let_me_download'] ?? '' }}</span></label>
@endif
</p>
<div><input type="submit" name="submit" id="continuedownload" style="font-size: 20pt; height: 40px;" value="{{ $lang_downloadnotice['submit_download_the_torrent'] ?? '' }}"@if (! empty($forcecheck)) disabled="disabled"@endif /></div>
</form>
</td>
</tr>
@endif
</table>
@endsection
