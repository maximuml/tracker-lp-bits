@php
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
$action = \App\Support\SupportContext::getPost('action') ?? \App\Support\SupportContext::getQuery('action') ?? 'dailylog';
$action = in_array($action, ['dailylog', 'chronicle', 'news', 'poll'], true) ? $action : 'dailylog';
$title = $title ?? match ($action) {
    'chronicle' => $lang_log['head_chronicle'] ?? 'Chronicle',
    'news' => $lang_log['head_news'] ?? 'News log',
    'poll' => $lang_log['head_previous_polls'] ?? 'Previous polls',
    default => $lang_log['head_site_log'] ?? 'Site log',
};
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('log._log')
@endsection
