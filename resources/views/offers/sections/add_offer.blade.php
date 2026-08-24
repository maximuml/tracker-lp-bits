@php
/** @var array<string, mixed> $add_offer */
/** @var array<string, mixed> $lang */
$lang_offers = $lang;
$add = $add_offer;
@endphp
<p>{{ $lang_offers['text_red_star_required'] ?? '' }}</p>
<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">
@csrf
<table width=100% border=0 cellspacing=0 cellpadding=5>
<tr><td class=colhead align=center colspan=2>{{ $lang_offers['text_offers_open_to_all'] ?? '' }}</td></tr>
<tr><td class=rowhead align=right><b>{{ $lang_offers['row_type'] ?? '' }}<font color=red>*</font></b></td><td class=rowfollow align=left> {!! $add['typeOptions'] !!}</td></tr>
<tr><td class=rowhead align=right><b>{{ $lang_offers['row_title'] ?? '' }}<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style="width: 99%;" /></td></tr>
<tr><td class=rowhead align=right><b>{{ $lang_offers['row_post_or_photo'] ?? '' }}</b></td><td class=rowfollow align=left><input type=text name=picture style="width: 99%;"><br />{{ $lang_offers['text_link_to_picture'] ?? '' }}</td></tr>
<tr><td class=rowhead align=right valign=top><b>{{ $lang_offers['row_description'] ?? '' }}<b><font color=red>*</font></td><td class=rowfollow align=left>
{!! $add['bbcodeEditor'] !!}
</td></tr>
<tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value={{ $lang_offers['submit_add_offer'] ?? 'Add' }} ></td></tr>
</table></form><br />
