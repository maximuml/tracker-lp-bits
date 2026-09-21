<h1>{{ $viewmessage['subject'] }}</h1>
@include('messages.sections._menu', ['selected' => $viewmessage['mailbox']])

<table data-nx="data"><caption class="nx-sr-only">{{ $viewmessage['subject'] }}</caption>
<tr>
<th class="colhead nx-w-50 nx-align-left" scope="col">{{ $viewmessage['from'] }}</th>
<th class="colhead nx-w-50 nx-align-left" scope="col">{{ __('legacy/messages.col_date') }}</th>
</tr>
<tr>
<td class="rowfollow">{{ $viewmessage['sender'] ?? '' }}</td>
<td class="rowfollow">{{ $viewmessage['added'] ?? '' }}&nbsp;&nbsp;@if ($viewmessage['showUnread'] ?? false)<span><b>{{ __('legacy/messages.text_new') }}</b></span>@endif</td>
</tr>
<tr>
<td colspan="2">{{ $viewmessage['body'] ?? '' }}</td>
</tr>
<tr>
<td>
@if (! $viewmessage['isSender'])
<form action="/messages" method="post">@csrf<input type="hidden" name="action" value="moveordel"><input type="hidden" name="id" value={{ $viewmessage['pmId'] }}>
<input type="submit" name="move" value={{ __('legacy/messages.submit_move_to') }}><select name="box" aria-label="{{ __('legacy/messages.submit_move_to') }}"><option value="1">{{ __('legacy/messages.text_inbox') }}</option>
@foreach ($viewmessage['moveBoxes'] ?? [] as $opt)
<option value="{{ $opt->value }}">{{ $opt->label }}</option>
@endforeach
</select></form>
@endif
</td><td class="nx-align-right"><span class="nx-color-white">[ <form action="/messages" method="post" class="nx-inline">@csrf<input type="hidden" name="action" value="deletemessage"><input type="hidden" name="id" value="{{ $viewmessage['pmId'] }}"><input type="submit" value="{{ __('legacy/messages.text_delete') }}"></form> ]@if ($viewmessage['replyHref'] ?? null) [ <a href="{{ $viewmessage['replyHref'] }}">{{ __('legacy/messages.text_reply') }}</a> ]@endif [ <a href="messages.php?action=forward&id={{ $viewmessage['pmId'] }}">{{ __('legacy/messages.text_forward_pm') }}</a> ]</span></td>
</tr>
</table>
