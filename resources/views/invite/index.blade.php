@php
$lang_invite = (array) (\App\Support\SupportContext::getGlobal('lang_invite') ?? []);
$title = $title ?? ($lang_invite['head_invites'] ?? 'Invites');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('invite._invite')
@endsection
