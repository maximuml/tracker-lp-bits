@php
$lang_news = (array) (\App\Support\SupportContext::getGlobal('lang_news') ?? []);
$action = \App\Support\SupportContext::getQuery('action') ?? '';
$newsid = (int) (\App\Support\SupportContext::getQuery('newsid') ?? 0);
$title = $title ?? ($action === 'edit' && $newsid > 0
    ? ($lang_news['head_edit_site_news'] ?? 'Edit site news')
    : ($lang_news['head_site_news'] ?? 'Site news'));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('news._news')
@endsection
