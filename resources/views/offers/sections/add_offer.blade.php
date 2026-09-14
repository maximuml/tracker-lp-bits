<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_red_star_required'] ?? ''))</p>
<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">
@csrf
<table width=100% border=0 cellspacing=0 cellpadding=5>
<tr><td class=colhead align=center colspan=2>{{ $lang['text_offers_open_to_all'] ?? '' }}</td></tr>
<tr><td class=rowhead align=right><b>{{ $lang['row_type'] ?? '' }}<font color=red>*</font></b></td><td class=rowfollow align=left> @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($add_offer['typeOptions'] ?? ''))</td></tr>
<tr><td class=rowhead align=right><b>{{ $lang['row_title'] ?? '' }}<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style="width: 99%;" /></td></tr>
<tr><td class=rowhead align=right><b>{{ $lang['row_post_or_photo'] ?? '' }}</b></td><td class=rowfollow align=left><input type=text name=picture style="width: 99%;"><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_link_to_picture'] ?? ''))</td></tr>
<tr><td class=rowhead align=right valign=top><b>{{ $lang['row_description'] ?? '' }}<b><font color=red>*</font></td><td class=rowfollow align=left>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($add_offer['bbcodeEditor'] ?? ''))
</td></tr>
<tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value={{ $lang['submit_add_offer'] ?? 'Add' }} ></td></tr>
</table></form><br />
