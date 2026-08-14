@php
$lang_messages = (array) (\App\Support\SupportContext::getGlobal('lang_messages') ?? []);
$title = $title ?? ($lang_messages['head_private_messages'] ?? 'Private messages');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('messages._messages')
@endsection
