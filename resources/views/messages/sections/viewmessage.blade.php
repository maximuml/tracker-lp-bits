<h1>{{ $viewmessage['subject'] }}</h1>
@include('messages.sections._menu', ['selected' => $viewmessage['mailbox']])

<table data-nx="data" width={{ $contentWidth }} border="0" cellpadding="4" cellspacing="0">
<tr>
<td width="50%" class="colhead" align="left">{{ $viewmessage['from'] }}</td>
<td width="50%" class="colhead" align="left">{{ __('legacy/messages.col_date') }}</td>
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
<input type="submit" name="move" value={{ __('legacy/messages.submit_move_to') }}><select name="box"><option value="1">{{ __('legacy/messages.text_inbox') }}</option>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['moveBoxOptions'] ?? ''))
</select></form>
@endif
</td><td align="right" ><font color=white>[ <form action="/messages" method="post" class="nx-inline">@csrf<input type="hidden" name="action" value="deletemessage"><input type="hidden" name="id" value="{{ $viewmessage['pmId'] }}"><input type="submit" value="{{ __('legacy/messages.text_delete') }}"></form> ]@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmessage['reply'] ?? '')) [ <a href="messages.php?action=forward&id={{ $viewmessage['pmId'] }}">{{ __('legacy/messages.text_forward_pm') }}</a> ]</font></td>
</tr>
</table>
