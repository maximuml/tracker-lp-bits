@include('messages.sections._menu', ['selected' => $viewmailbox['mailbox']])

<div>
<div class="nx-colhead">{{ __('legacy/messages.col_search_message') }}</div>
<div class="text-center p-[5px]">@include('messages.sections._jump_to')</div>
</div>

@if (! $viewmailbox['hasMessages'])
<x-empty-state :title="__('legacy/messages.text_no_messages')" />
@else
{{ $viewmailbox['pagertop'] ?? '' }}
<form action="/messages" method="post">
@csrf
<input type="hidden" name="action" value="moveordel">
<div class="nx-maillist">
<div class="nx-maillist__tools">
	<input class="nx-postbtn" type="button" data-checkall data-label-check="{{ __('legacy/messages.input_check_all') }}" data-label-uncheck="{{ __('legacy/messages.input_uncheck_all') }}" value="{{ __('legacy/messages.input_check_all') }}">
	<span class="nx-maillist__filters">
		<a href="messages.php?action=viewmailbox&amp;box={{ $viewmailbox['mailbox'] }}&amp;unread=yes">{{ __('legacy/messages.text_unread_messages') }}</a> &middot;
		<a href="messages.php?action=viewmailbox&amp;box={{ $viewmailbox['mailbox'] }}&amp;unread=no">{{ __('legacy/messages.text_read_messages') }}</a> &middot;
		<a href="messages.php?action=editmailboxes">{{ __('legacy/messages.text_mailbox_manager') }}</a>
	</span>
</div>
@foreach ($viewmailbox['rows'] as $row)
<div class="nx-mail{{ $row['unread'] ? ' nx-mail--unread' : '' }}">
	<span class="nx-mail__dot" title="{{ $row['unread'] ? __('legacy/messages.title_unread') : __('legacy/messages.title_read') }}"></span>
	<a class="nx-mail__subject" href="messages.php?action=viewmessage&amp;id={{ $row['id'] }}">{{ $row['subject'] }}</a>
	<span class="nx-mail__user">{{ $row['username'] ?? '' }}</span>
	<span class="nx-mail__date">{{ $row['added'] ?? '' }}</span>
	<input class="nx-mail__check" type="checkbox" name="messages[]" value="{{ $row['id'] }}" aria-label="{{ __('legacy/messages.col_subject') }}: {{ $row['subject'] }}">
</div>
@endforeach
<div class="nx-bulkbar" data-bulkbar hidden>
	<span class="nx-bulkbar__count"><b data-bulk-count>0</b> {{ __('legacy/messages.text_selected') }}</span>
	@if (! $viewmailbox['isSentBox'])
	<input class="nx-postbtn" type="submit" name="markread" value="{{ __('legacy/messages.submit_mark_as_read') }}">
	@endif
	<input class="nx-postbtn nx-postbtn--danger" type="submit" name="delete" value="{{ __('legacy/messages.submit_delete') }}">
	@if (! $viewmailbox['isSentBox'])
	{{ __('legacy/messages.text_or') }}
	<input class="nx-postbtn" type="submit" name="move" value="{{ __('legacy/messages.submit_move_to') }}">
	<select name="box" aria-label="{{ __('legacy/messages.submit_move_to') }}"><option value="1">{{ __('legacy/messages.text_inbox') }}</option>
	@foreach ($viewmailbox['moveBoxes'] as $opt)
	<option value="{{ $opt->value }}">{{ $opt->label }}</option>
	@endforeach
	</select>
	@endif
</div>
</div>
</form>
{{ $viewmailbox['pagerbottom'] ?? '' }}
@endif
