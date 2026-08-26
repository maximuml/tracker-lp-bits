@php
$lang_messages = (array) (\app(\App\Support\Globals::class)->get('lang_messages') ?? []);
$title = $title ?? ($lang_messages['head_private_messages'] ?? 'Private messages');
$action = $action ?? 'viewmailbox';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
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
