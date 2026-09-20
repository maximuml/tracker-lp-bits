@include('messages.sections._menu', ['selected' => $viewmailbox['mailbox']])

<div>
<div class="nx-colhead">{{ __('legacy/messages.col_search_message') }}</div>
<div class="nx-center nx-cell-5">@include('messages.sections._jump_to')</div>
</div>

@if (! $viewmailbox['hasMessages'])
<p align="center">{{ __('legacy/messages.text_no_messages') }}</p>
@else
{{ $viewmailbox['pagertop'] ?? '' }}
<form action="/messages" method="post">
@csrf
<input type="hidden" name="action" value="moveordel">
<table data-nx="data" border="0" cellpadding="4" cellspacing="0" width={{ $contentWidth }} align="center">
<tr>
<th width="1%" class="colhead" align="center" scope="col">{{ __('legacy/messages.col_status') }}</th>
<th class="colhead" align="left" scope="col">{{ __('legacy/messages.col_subject') }} </th>
<th width="35%" class="colhead" align="left" scope="col">{{ $viewmailbox['senderReceiver'] }}</th>
<th width="1%" class="colhead" align="center" scope="col"><img class="time" src="pic/trans.gif" alt="time" title="{{ __('legacy/messages.col_date') }}" /></th>
<th width="1%" class="colhead" align="center" scope="col">{{ __('legacy/messages.col_act') }}</th>
</tr>
@foreach ($viewmailbox['rows'] as $row)
<tr>
<td class=rowfollow align=center>@if ($row['unread'])<img class="unreadpm" src="pic/trans.gif" alt="Unread" title="{{ __('legacy/messages.title_unread') }}" />@else<img class="readpm" src="pic/trans.gif" alt="Read" title="{{ __('legacy/messages.title_read') }}" />@endif</td>
<td class=rowfollow align=left><a href="messages.php?action=viewmessage&id={{ $row['id'] }}">{{ $row['subject'] }}</a></td>
<td class=rowfollow align=left>{{ $row['username'] ?? '' }}</td>
<td class=rowfollow nowrap>{{ $row['added'] ?? '' }}</td>
<td class=rowfollow><input class=checkbox type="checkbox" name="messages[]" value="{{ $row['id'] }}" aria-label="{{ __('legacy/messages.col_subject') }}: {{ $row['subject'] }}"></td>
</tr>
@endforeach
<tr class="colhead">
<th colspan="5" align="right" class="colhead" scope="colgroup"><input class=btn type="button" data-checkall data-label-check="{{ __('legacy/messages.input_check_all') }}" data-label-uncheck="{{ __('legacy/messages.input_uncheck_all') }}" value="{{ __('legacy/messages.input_check_all') }}">
@if (! $viewmailbox['isSentBox'])
<input class=btn type="submit" name="markread" value="{{ __('legacy/messages.submit_mark_as_read') }}">
@endif
<input class=btn type="submit" name="delete" value="{{ __('legacy/messages.submit_delete') }}">
@if (! $viewmailbox['isSentBox'])
{{ __('legacy/messages.text_or') }}
<input class=btn type="submit" name="move" value="{{ __('legacy/messages.submit_move_to') }}"> <select name="box" aria-label="{{ __('legacy/messages.submit_move_to') }}"><option value="1">{{ __('legacy/messages.text_inbox') }}</option>
@foreach ($viewmailbox['moveBoxes'] as $opt)
<option value="{{ $opt->value }}">{{ $opt->label }}</option>
@endforeach
@endif
</select>
</th>
</tr>
</form><tr><td class=toolbox colspan=5>
<div align="center"><img class="unreadpm" src="pic/trans.gif" alt="Unread" title="{{ __('legacy/messages.title_unread') }}" /><a href="messages.php?action=viewmailbox&box={{ $viewmailbox['mailbox'] }}&unread=yes">{{ __('legacy/messages.text_unread_messages') }}</a>
<img class="readpm" src="pic/trans.gif" alt="Read" title="{{ __('legacy/messages.title_read') }}" /><a href="messages.php?action=viewmailbox&box={{ $viewmailbox['mailbox'] }}&unread=no">{{ __('legacy/messages.text_read_messages') }}</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<a href="messages.php?action=editmailboxes"><b>{{ __('legacy/messages.text_mailbox_manager') }}</a></b></div></td></tr></table>
@endif
