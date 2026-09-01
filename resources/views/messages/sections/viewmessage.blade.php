@php
/** @var array<string, mixed> $viewmessage */
/** @var array<string, mixed> $lang */
$lang_messages = $lang;
$CONTENT_WIDTH = $contentWidth;
$vm = $viewmessage;
@endphp
<h1>{{ $vm['subject'] }}</h1>
@include('messages.sections._menu', ['selected' => $vm['mailbox']])

<table width={{ $CONTENT_WIDTH }} border="0" cellpadding="4" cellspacing="0">
<tr>
<td width="50%" class="colhead" align="left">{{ $vm['from'] }}</td>
<td width="50%" class="colhead" align="left">{{ $lang_messages['col_date'] ?? 'Date' }}</td>
</tr>
<tr>
<td class="rowfollow" align="left">{!! $vm['sender'] !!}</td>
<td class="rowfollow" align="left">{{ $vm['added'] }}&nbsp;&nbsp;{!! $vm['unread'] !!}</td>
</tr>
<tr>
<td colspan="2" align="left">{!! $vm['body'] !!}</td>
</tr>
<tr>
<td align=left>
@if (! $vm['isSender'])
<form action="messages.php" method="post">@csrf<input type="hidden" name="action" value="moveordel"><input type="hidden" name="id" value={{ $vm['pmId'] }}>
<input type="submit" name="move" value={{ $lang_messages['submit_move_to'] ?? 'Move to' }}><select name="box"><option value="1">{{ $lang_messages['text_inbox'] ?? 'Inbox' }}</option>
{!! $vm['moveBoxOptions'] !!}
</select></form>
@endif
</td><td align="right" ><font color=white>[ <form action="messages.php" method="post" style="display:inline;">@csrf<input type="hidden" name="action" value="deletemessage"><input type="hidden" name="id" value="{{ $vm['pmId'] }}"><input type="submit" value="{{ $lang_messages['text_delete'] ?? 'Delete' }}" style="display:inline;"></form> ]{!! $vm['reply'] !!} [ <a href="messages.php?action=forward&id={{ $vm['pmId'] }}">{{ $lang_messages['text_forward_pm'] ?? 'Forward' }}</a> ]</font></td>
</tr>
</table>
