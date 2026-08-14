@php
$lang_friends = (array) (\App\Support\SupportContext::getGlobal('lang_friends') ?? []);
$user = (array) (\App\Support\SupportContext::getUser() ?? []);
$title = $title ?? (($lang_friends['head_personal_lists_for'] ?? 'Personal lists for ') . ($user['username'] ?? ''));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('friends._friends')
@endsection
