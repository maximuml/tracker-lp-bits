@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? 'Private messages')

@section('content')
<h1 class="nx-sr-only">{{ $title ?? 'Private messages' }}</h1>
@if ($action === 'viewmessage')
@include('messages.sections._menu', ['selected' => $viewmessage['mailbox']])
<div class="nx-msgsplit">
	<div class="nx-msgsplit__list">
	@foreach ($viewmailbox['rows'] ?? [] as $row)
		<a class="nx-mail nx-mail--compact{{ $row['unread'] ? ' nx-mail--unread' : '' }}{{ $row['id'] === $viewmessage['pmId'] ? ' nx-mail--active' : '' }}" href="/web/messages?action=viewmessage&amp;id={{ $row['id'] }}">
			<span class="nx-mail__dot"></span>
			<span class="nx-mail__main"><span class="nx-mail__subject">{{ $row['subject'] }}</span><span class="nx-mail__sub">{{ $row['username'] ?? '' }} &middot; {{ $row['added'] ?? '' }}</span></span>
		</a>
	@endforeach
	@if (empty($viewmailbox['rows']))
		<div class="nx-maillist__empty">{{ __('messages.text_no_messages') }}</div>
	@endif
	</div>
	<div class="nx-msgsplit__pane">@include('messages.sections.viewmessage')</div>
</div>
@elseif ($action === 'forward')
@include('messages.sections.forward')
@elseif ($action === 'editmailboxes')
@include('messages.sections.editmailboxes')
@else
@include('messages.sections.viewmailbox')
@endif
@endsection
