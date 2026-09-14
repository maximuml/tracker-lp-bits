<form id="compose" method="post" name="compose" action="?id={{ $edit_offer['id'] }}&amp;take_off_edit=1">
@csrf
<table width="97%" cellspacing="0" cellpadding="3">
<tr><td class="colhead" align="center" colspan="2">{{ $lang['text_edit_offer'] ?? 'Edit offer' }}</td></tr>
<tr><td class="rowhead" align="right">{{ $lang['row_type'] ?? '' }}<font color="red">*</font></td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($edit_offer['catSelect'] ?? ''))</td></tr>
<tr><td class="rowhead" align="right">{{ $lang['row_title'] ?? '' }}<font color="red">*</font></td><td class="rowfollow" align="left"><input type="text" style="width: 99%" name="name" value="{{ $edit_offer['title'] }}" /></td></tr>
<tr><td class="rowhead" align="right">{{ $lang['row_post_or_photo'] ?? '' }}</td><td class="rowfollow" align="left"><input type="text" name="picture" style="width: 99%" value='' /><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_link_to_picture'] ?? ''))</td></tr>
<tr><td class="rowhead" align="right" valign="top"><b>{{ $lang['row_description'] ?? '' }}<font color="red">*</font></b></td><td class="rowfollow" align="left">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($edit_offer['bbcodeEditor'] ?? ''))
</td></tr>
<tr><td class="toolbox" style="vertical-align: middle; padding-top: 10px; padding-bottom: 10px;" align="center" colspan="2"><input id="qr" type="submit" value="{{ $lang['submit_edit_offer'] ?? 'Edit' }}" class="btn" /></td></tr>
</table></form><br />
