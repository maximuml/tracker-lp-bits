<div class="nx-msgview">
	<a class="nx-msgview__back" href="{{ $viewmessage['backHref'] }}">‹ {{ $viewmailbox['mailboxName'] ?? __('messages.text_inbox') }}</a>
	<header class="nx-msgview__head">
		<h1 class="nx-msgview__subject">{{ $viewmessage['subject'] }}</h1>
		<div class="nx-msgview__meta">{{ $viewmessage['from'] }} <b>{{ $viewmessage['sender'] ?? '' }}</b> &middot; {{ $viewmessage['added'] ?? '' }}@if ($viewmessage['showUnread'] ?? false) <b>{{ __('messages.text_new') }}</b>@endif</div>
		<div class="nx-msgview__actions">
			@if ($viewmessage['replyHref'] ?? null)
				<a class="nx-btn nx-btn--primary" href="{{ $viewmessage['replyHref'] }}">{{ __('messages.text_reply') }}</a>
			@endif
			<a class="nx-postbtn" href="/web/messages?action=forward&amp;id={{ $viewmessage['pmId'] }}">{{ __('messages.text_forward_pm') }}</a>
			@if (! $viewmessage['isSender'])
			<form action="/web/messages/move-or-delete" method="post" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $viewmessage['pmId'] }}"><input class="nx-postbtn" type="submit" name="move" value="{{ __('messages.submit_move_to') }}"><select name="box" aria-label="{{ __('messages.submit_move_to') }}"><option value="1">{{ __('messages.text_inbox') }}</option>
			@foreach ($viewmessage['moveBoxes'] ?? [] as $opt)
			<option value="{{ $opt->value }}">{{ $opt->label }}</option>
			@endforeach
			</select></form>
			@endif
			<form action="/web/messages/delete" method="post" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $viewmessage['pmId'] }}"><input class="nx-postbtn nx-postbtn--danger" type="submit" value="{{ __('messages.text_delete') }}"></form>
		</div>
	</header>
	<div class="nx-msgview__body">{{ $viewmessage['body'] ?? '' }}</div>
	@if ($viewmessage['quickReply'] ?? null)
		<form class="nx-quickreply" method="post" action="/web/messages/send">
			@csrf
			<input type="hidden" name="receiver" value="{{ $viewmessage['quickReply']['receiver'] }}">
			<input type="hidden" name="origmsg" value="{{ $viewmessage['quickReply']['origmsg'] }}">
			<input type="hidden" name="subject" value="{{ $viewmessage['quickReply']['subject'] }}">
			<input type="hidden" name="returnto" value="{{ $viewmessage['quickReply']['returnto'] }}">
			@if ($viewmessage['quickReply']['save'])
				<input type="hidden" name="save" value="yes">
			@endif
			<label for="quickreply-body">{{ __('messages.text_quick_reply') }}</label>
			<textarea id="quickreply-body" name="body" rows="4" required></textarea>
			<button type="submit" class="nx-btn nx-btn--primary" data-submit-once>{{ __('messages.submit_send') }}</button>
		</form>
	@endif
</div>
