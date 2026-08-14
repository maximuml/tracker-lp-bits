@php
$lang_sendmessage = (array) (\App\Support\SupportContext::getGlobal('lang_sendmessage') ?? []);
$title = $title ?? ($lang_sendmessage['head_send_message'] ?? 'Send message');
$stdheadMsgalert = false;
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('sendmessage._sendmessage')
@endsection
