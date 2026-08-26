@php
$lang_mybonus = (array) (\App\Support\SupportContext::getGlobal('lang_mybonus') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$title = $title ?? (($CURUSER['username'] ?? '') . ($lang_mybonus['head_karma_page'] ?? ' - Karma'));
$action = $action ?? '';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('my.sections.bonus')
@endsection
