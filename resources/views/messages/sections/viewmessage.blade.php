<h1>{{ $viewmessage['subject'] }}</h1>
@include('messages.sections._menu', ['selected' => $viewmessage['mailbox']])

<table width={{ $contentWidth }} border="0" cellpadding="4" cellspacing="0">
<tr>
<td width="50%" class="colhead" align="left">{{ $viewmessage['from'] }}</td>
<td width="50%" class="colhead" align="left">{{ $lang['col_date'] ?? 'Date' }}</td>
</tr>
<tr>
<td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['sender'] ?? ''))</td>
<td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['added'] ?? ''))&nbsp;&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['unread'] ?? ''))</td>
</tr>
<tr>
<td colspan="2" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['body'] ?? ''))</td>
</tr>
<tr>
<td align=left>
@if (! $viewmessage['isSender'])
<form action="/messages" method="post">@csrf<input type="hidden" name="action" value="moveordel"><input type="hidden" name="id" value={{ $viewmessage['pmId'] }}>
<input type="submit" name="move" value={{ $lang['submit_move_to'] ?? 'Move to' }}><select name="box"><option value="1">{{ $lang['text_inbox'] ?? 'Inbox' }}</option>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['moveBoxOptions'] ?? ''))
</select></form>
@endif
</td><td align="right" ><font color=white>[ <form action="/messages" method="post" class="nx-inline">@csrf<input type="hidden" name="action" value="deletemessage"><input type="hidden" name="id" value="{{ $viewmessage['pmId'] }}"><input type="submit" value="{{ $lang['text_delete'] ?? 'Delete' }}"></form> ]@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['reply'] ?? '')) [ <a href="messages.php?action=forward&id={{ $viewmessage['pmId'] }}">{{ $lang['text_forward_pm'] ?? 'Forward' }}</a> ]</font></td>
</tr>
</table>
