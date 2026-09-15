@include('messages.sections._menu', ['selected' => $viewmailbox['mailbox']])

<table border="0" cellpadding="4" cellspacing="0" width={{ $contentWidth }} align="center">
<tr><td class=colhead align=left>{{ $lang['col_search_message'] ?? 'Search message' }}</td></tr>
<tr><td class=toolbox align=center>@include('messages.sections._jump_to')</td></tr>
</table>

@if (! $viewmailbox['hasMessages'])
<p align="center">{{ $lang['text_no_messages'] ?? 'No messages' }}</p>
@else
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmailbox['pagertop'] ?? ''))
<form action="/messages" method="post">
@csrf
<input type="hidden" name="action" value="moveordel">
<table data-nx="data" border="0" cellpadding="4" cellspacing="0" width={{ $contentWidth }} align="center">
<tr>
<td width="1%" class="colhead" align="center">{{ $lang['col_status'] ?? 'Status' }}</td>
<td class="colhead" align="left">{{ $lang['col_subject'] ?? 'Subject' }} </td>
<td width="35%" class="colhead" align="left">{{ $viewmailbox['senderReceiver'] }}</td>
<td width="1%" class="colhead" align="center"><img class="time" src="pic/trans.gif" alt="time" title="{{ $lang['col_date'] ?? 'Date' }}" /></td>
<td width="1%" class="colhead" align="center">{{ $lang['col_act'] ?? 'Act' }}</td>
</tr>
@foreach ($viewmailbox['rows'] as $row)
<tr>
<td class=rowfollow align=center>@if ($row['unread'])<img class="unreadpm" src="pic/trans.gif" alt="Unread" title="{{ $lang['title_unread'] ?? '' }}" />@else<img class="readpm" src="pic/trans.gif" alt="Read" title="{{ $lang['title_read'] ?? '' }}" />@endif</td>
<td class=rowfollow align=left><a href="messages.php?action=viewmessage&id={{ $row['id'] }}">{{ $row['subject'] }}</a></td>
<td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username'] ?? ''))</td>
<td class=rowfollow nowrap>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['added'] ?? ''))</td>
<td class=rowfollow><input class=checkbox type="checkbox" name="messages[]" value="{{ $row['id'] }}"></td>
</tr>
@endforeach
<tr class="colhead">
<td colspan="5" align="right" class="colhead"><input class=btn type="button" data-checkall data-label-check="{{ $lang['input_check_all'] ?? 'Check all' }}" data-label-uncheck="{{ $lang['input_uncheck_all'] ?? 'Uncheck all' }}" value="{{ $lang['input_check_all'] ?? 'Check all' }}">
@if (! $viewmailbox['isSentBox'])
<input class=btn type="submit" name="markread" value="{{ $lang['submit_mark_as_read'] ?? 'Mark as read' }}">
@endif
<input class=btn type="submit" name="delete" value="{{ $lang['submit_delete'] ?? 'Delete' }}">
@if (! $viewmailbox['isSentBox'])
{{ $lang['text_or'] ?? 'or' }}
<input class=btn type="submit" name="move" value="{{ $lang['submit_move_to'] ?? 'Move to' }}"> <select name="box"><option value="1">{{ $lang['text_inbox'] ?? 'Inbox' }}</option>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmailbox['moveBoxOptions'] ?? ''))
@endif
</select>
</td>
</tr>
</form><tr><td class=toolbox colspan=5>
<div align="center"><img class="unreadpm" src="pic/trans.gif" alt="Unread" title="{{ $lang['title_unread'] ?? 'Unread' }}" /><a href="messages.php?action=viewmailbox&box={{ $viewmailbox['mailbox'] }}&unread=yes">{{ $lang['text_unread_messages'] ?? 'Unread' }}</a>
<img class="readpm" src="pic/trans.gif" alt="Read" title="{{ $lang['title_read'] ?? 'Read' }}" /><a href="messages.php?action=viewmailbox&box={{ $viewmailbox['mailbox'] }}&unread=no">{{ $lang['text_read_messages'] ?? 'Read' }}</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="messages.php?action=editmailboxes"><b>{{ $lang['text_mailbox_manager'] ?? 'Mailbox manager' }}</a></b></div></td></tr></table>
@endif
