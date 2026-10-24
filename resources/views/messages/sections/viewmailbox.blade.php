@include('components.messages.sections.menu', ['selected' => $viewmailbox['mailbox']])

@include('components.messages.sections.jump_to')

@if (! $viewmailbox['hasMessages'])
<x-empty-state :title="__('messages.text_no_messages')" />
@else
{{ $viewmailbox['pagertop'] ?? '' }}
<form action="/web/messages/move-or-delete" method="post">
@csrf
<div class="nx-maillist">
<div class="nx-maillist__tools">
	<input class="nx-postbtn" type="button" data-checkall data-label-check="{{ __('messages.input_check_all') }}" data-label-uncheck="{{ __('messages.input_uncheck_all') }}" value="{{ __('messages.input_check_all') }}">
	<span class="nx-maillist__filters">
		<a class="nx-chip{{ $viewmailbox['unread'] === '' ? ' nx-chip--on' : '' }}" href="/web/messages?action=viewmailbox&amp;box={{ $viewmailbox['mailbox'] }}" @if ($viewmailbox['unread'] === '') aria-current="true" @endif>{{ __('messages.text_all_messages') }}</a>
		<a class="nx-chip{{ in_array($viewmailbox['unread'], ['yes', '1'], true) ? ' nx-chip--on' : '' }}" href="/web/messages?action=viewmailbox&amp;box={{ $viewmailbox['mailbox'] }}&amp;unread=yes" @if (in_array($viewmailbox['unread'], ['yes', '1'], true)) aria-current="true" @endif>{{ __('messages.text_unread') }}</a>
		<a class="nx-chip{{ in_array($viewmailbox['unread'], ['no', '0'], true) ? ' nx-chip--on' : '' }}" href="/web/messages?action=viewmailbox&amp;box={{ $viewmailbox['mailbox'] }}&amp;unread=no" @if (in_array($viewmailbox['unread'], ['no', '0'], true)) aria-current="true" @endif>{{ __('messages.text_read') }}</a>
	</span>
</div>
@foreach ($viewmailbox['rows'] as $row)
<div class="nx-mail{{ $row['unread'] ? ' nx-mail--unread' : '' }}">
	<span class="nx-mail__dot" title="{{ $row['unread'] ? __('messages.title_unread') : __('messages.title_read') }}"></span>
	<a class="nx-mail__subject" href="/web/messages?action=viewmessage&amp;id={{ $row['id'] }}">{{ $row['subject'] }}</a>
	<span class="nx-mail__user">{{ $row['username'] ?? '' }}</span>
	<span class="nx-mail__date">{{ $row['added'] ?? '' }}</span>
	<input class="nx-mail__check" type="checkbox" name="messages[]" value="{{ $row['id'] }}" aria-label="{{ __('messages.col_subject') }}: {{ $row['subject'] }}">
</div>
@endforeach
<div class="nx-bulkbar" data-bulkbar hidden>
	<span class="nx-bulkbar__count"><b data-bulk-count>0</b> {{ __('messages.text_selected') }}</span>
	@if (! $viewmailbox['isSentBox'])
	<input class="nx-postbtn" type="submit" name="markread" value="{{ __('messages.submit_mark_as_read') }}">
	@endif
	<input class="nx-postbtn nx-postbtn--danger" type="submit" name="delete" value="{{ __('messages.submit_delete') }}">
	@if (! $viewmailbox['isSentBox'])
	{{ __('messages.text_or') }}
	<input class="nx-postbtn" type="submit" name="move" value="{{ __('messages.submit_move_to') }}">
	<select name="box" aria-label="{{ __('messages.submit_move_to') }}"><option value="1">{{ __('messages.text_inbox') }}</option>
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
