@php
/** @var array<string, mixed> $edit_offer */
/** @var array<string, mixed> $lang */
$lang_offers = $lang;
$e = $edit_offer;
@endphp
<form id="compose" method="post" name="compose" action="?id={{ $e['id'] }}&amp;take_off_edit=1">
@csrf
<table width="97%" cellspacing="0" cellpadding="3">
<tr><td class="colhead" align="center" colspan="2">{{ $lang_offers['text_edit_offer'] ?? 'Edit offer' }}</td></tr>
<tr><td class="rowhead" align="right">{{ $lang_offers['row_type'] ?? '' }}<font color="red">*</font></td><td class="rowfollow" align="left">{!! $e['catSelect'] !!}</td></tr>
<tr><td class="rowhead" align="right">{{ $lang_offers['row_title'] ?? '' }}<font color="red">*</font></td><td class="rowfollow" align="left"><input type="text" style="width: 99%" name="name" value="{{ $e['title'] }}" /></td></tr>
<tr><td class="rowhead" align="right">{{ $lang_offers['row_post_or_photo'] ?? '' }}</td><td class="rowfollow" align="left"><input type="text" name="picture" style="width: 99%" value='' /><br />{{ $lang_offers['text_link_to_picture'] ?? '' }}</td></tr>
<tr><td class="rowhead" align="right" valign="top"><b>{{ $lang_offers['row_description'] ?? '' }}<font color="red">*</font></b></td><td class="rowfollow" align="left">
{!! $e['bbcodeEditor'] !!}
</td></tr>
<tr><td class="toolbox" style="vertical-align: middle; padding-top: 10px; padding-bottom: 10px;" align="center" colspan="2"><input id="qr" type="submit" value="{{ $lang_offers['submit_edit_offer'] ?? 'Edit' }}" class="btn" /></td></tr>
</table></form><br />
