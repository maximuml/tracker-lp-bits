<h1 align="center">{{ $lang['text_forward_pm'] ?? 'Forward PM' }}</h1>
<table border="0" cellpadding="4" cellspacing="0"  width={{ $contentWidth }}>
<form action="/takemessage" method="post">
@csrf
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="{{ $forward['pmId'] }}">
<tr>
<td class="rowhead" align="right">{{ $lang['row_to'] ?? 'To' }}</td>
<td class="rowfollow" align=left><input type="text" name="to" style="width: 200px"></td>
</tr>
<tr>
<td class="rowhead" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_original_receiver'] ?? 'Original receiver'))</td>
<td class="rowfollow" align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['fromName'] ?? ''))</td>
</tr>
<tr>
<td class="rowhead" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_original_sender'] ?? 'Original sender'))</td>
<td class="rowfollow" align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['origName'] ?? ''))</td>
</tr>
<tr>
<td class="rowhead" align="right">{{ $lang['row_subject'] ?? 'Subject' }}</td>
<td class="rowfollow" align=left><input type="text" name="subject" value="{{ $forward['subject'] }}" style="width: 500px"></td>
</tr>
<tr>
<td class="rowhead" align="right" valign="top"><nobr>{{ $lang['row_message'] ?? 'Message' }}</nobr></td>
<td class="rowfollow" align=left><textarea name="body" style="width: 500px" rows="8"></textarea><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['body'] ?? ''))</td>
</tr>
<tr>
<td class=toolbox colspan="2" align="center"><input class=checkbox type="checkbox" name="save" value="yes"{{ \App\Support\LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '' }}>{{ $lang['checkbox_save_message'] ?? 'Save message' }}&nbsp;
<input type="submit" class="btn" value={{ $lang['submit_forward'] ?? 'Forward' }}></td>
</tr>
</table>
</form>
