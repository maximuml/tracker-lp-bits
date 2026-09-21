@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? 'Private messages')

@section('content')
<h1 class="nx-sr-only">{{ $title ?? 'Private messages' }}</h1>
@if ($action === 'viewmessage')
@include('messages.sections.viewmessage')
@elseif ($action === 'forward')
@include('messages.sections.forward')
@elseif ($action === 'editmailboxes')
@include('messages.sections.editmailboxes')
@else
@include('messages.sections.viewmailbox')
@endif
@endsection
