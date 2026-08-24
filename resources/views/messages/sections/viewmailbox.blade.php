@php
/** @var array<string, mixed> $viewmailbox */
/** @var array<string, mixed> $lang */
$lang_messages = $lang;
$CONTENT_WIDTH = $contentWidth;
$vm = $viewmailbox;
@endphp
@include('messages.sections._menu', ['selected' => $vm['mailbox']])

<table border="0" cellpadding="4" cellspacing="0" width={{ $CONTENT_WIDTH }}>
<tr><td class=colhead align=left>{{ $lang_messages['col_search_message'] ?? 'Search message' }}</td></tr>
<tr><td class=toolbox align=center>@include('messages.sections._jump_to')</td></tr>
</table>

@if (! $vm['hasMessages'])
<p align="center">{{ $lang_messages['text_no_messages'] ?? 'No messages' }}</p>
@else
{!! $vm['pagertop'] !!}
<form action="messages.php" method="post">
@csrf
<input type="hidden" name="action" value="moveordel">
<table border="0" cellpadding="4" cellspacing="0" width={{ $CONTENT_WIDTH }}>
<tr>
<td width="1%" class="colhead" align="center">{{ $lang_messages['col_status'] ?? 'Status' }}</td>
<td class="colhead" align="left">{{ $lang_messages['col_subject'] ?? 'Subject' }} </td>
<td width="35%" class="colhead" align="left">{{ $vm['senderReceiver'] }}</td>
<td width="1%" class="colhead" align="center"><img class="time" src="pic/trans.gif" alt="time" title="{{ $lang_messages['col_date'] ?? 'Date' }}" /></td>
<td width="1%" class="colhead" align="center">{{ $lang_messages['col_act'] ?? 'Act' }}</td>
</tr>
@foreach ($vm['rows'] as $row)
@php
$unreadImg = $row['unread'] === 'yes'
    ? '<img class="unreadpm" src="pic/trans.gif" alt="Unread" title="'.htmlspecialchars($lang_messages['title_unread'] ?? '').'" />'
    : '<img class="readpm" src="pic/trans.gif" alt="Read" title="'.htmlspecialchars($lang_messages['title_read'] ?? '').'" />';
@endphp
<tr>
<td class=rowfollow align=center>{!! $unreadImg !!}</td>
<td class=rowfollow align=left><a href="messages.php?action=viewmessage&id={{ $row['id'] }}">{{ $row['subject'] }}</a></td>
<td class=rowfollow align=left>{!! $row['username'] !!}</td>
<td class=rowfollow nowrap>{{ $row['added'] }}</td>
<td class=rowfollow><input class=checkbox type="checkbox" name="messages[]" value="{{ $row['id'] }}"></td>
</tr>
@endforeach
<tr class="colhead">
<td colspan="5" align="right" class="colhead"><input class=btn type="button" value="{{ $lang_messages['input_check_all'] ?? 'Check all' }}" onClick="this.value=check(form,'{{ $lang_messages['input_check_all'] ?? 'Check all' }}','{{ $lang_messages['input_uncheck_all'] ?? 'Uncheck all' }}')">
@if (! $vm['isSentBox'])
<input class=btn type="submit" name="markread" value="{{ $lang_messages['submit_mark_as_read'] ?? 'Mark as read' }}">
@endif
<input class=btn type="submit" name="delete" value={{ $lang_messages['submit_delete'] ?? 'Delete' }}>
@if (! $vm['isSentBox'])
{{ $lang_messages['text_or'] ?? 'or' }}
<input class=btn type="submit" name="move" value="{{ $lang_messages['submit_move_to'] ?? 'Move to' }}"> <select name="box"><option value="1">{{ $lang_messages['text_inbox'] ?? 'Inbox' }}</option>
{!! $vm['moveBoxOptions'] !!}
@endif
</select>
</td>
</tr>
</form><tr><td class=toolbox colspan=5>
<div align="center"><img class="unreadpm" src="pic/trans.gif" alt="Unread" title="{{ $lang_messages['title_unread'] ?? 'Unread' }}" /><a href="messages.php?action=viewmailbox&box={{ $vm['mailbox'] }}&unread=yes">{{ $lang_messages['text_unread_messages'] ?? 'Unread' }}</a>
<img class="readpm" src="pic/trans.gif" alt="Read" title="{{ $lang_messages['title_read'] ?? 'Read' }}" /><a href="messages.php?action=viewmailbox&box={{ $vm['mailbox'] }}&unread=no">{{ $lang_messages['text_read_messages'] ?? 'Read' }}</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="messages.php?action=editmailboxes"><b>{{ $lang_messages['text_mailbox_manager'] ?? 'Mailbox manager' }}</a></b></div></td></tr></table>
@endif
