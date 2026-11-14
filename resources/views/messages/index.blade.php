@extends('layouts.app')

@section('title', $title ?? 'Private messages')

@section('content')
<h1 class="nx-sr-only">{{ $title ?? 'Private messages' }}</h1>
@if ($vm->action === 'viewmessage')
@include('components.messages.sections.menu', ['selected' => $vm->viewmessage['mailbox'], 'viewmailbox' => $vm->viewmailbox])
<div class="nx-msgsplit">
	<div class="nx-msgsplit__list">
	@foreach ($vm->viewmailbox['rows'] ?? [] as $row)
		<a class="nx-mail nx-mail--compact{{ $row['unread'] ? ' nx-mail--unread' : '' }}{{ $row['id'] === $vm->viewmessage['pmId'] ? ' nx-mail--active' : '' }}" href="/web/messages?action=viewmessage&amp;id={{ $row['id'] }}">
			<span class="nx-mail__dot"></span>
			<span class="nx-mail__main"><span class="nx-mail__subject">{{ $row['subject'] }}</span><span class="nx-mail__sub">{{ $row['username'] ?? '' }} &middot; {{ $row['added'] ?? '' }}</span></span>
		</a>
	@endforeach
	@if (empty($vm->viewmailbox['rows']))
		<x-empty-state :title="__('messages.text_no_messages')" />
	@endif
	</div>
	<div class="nx-msgsplit__pane">@include('messages.sections.viewmessage', ['viewmessage' => $vm->viewmessage, 'viewmailbox' => $vm->viewmailbox])</div>
</div>
@elseif ($vm->action === 'forward')
@include('messages.sections.forward', ['forward' => $vm->forward, 'curUser' => $vm->curUser])
@elseif ($vm->action === 'editmailboxes')
@include('messages.sections.editmailboxes', ['editmailboxes' => $vm->editmailboxes])
@else
@include('messages.sections.viewmailbox', ['viewmailbox' => $vm->viewmailbox])
@endif
@endsection
