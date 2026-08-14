@php
$lang_mybonus = (array) (\App\Support\SupportContext::getGlobal('lang_mybonus') ?? []);
$CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$title = $title ?? (($CURUSER['username'] ?? '') . ($lang_mybonus['head_karma_page'] ?? ' - Karma'));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('my._bonus')
@endsection
