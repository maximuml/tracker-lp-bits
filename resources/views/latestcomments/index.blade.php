@php
$lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);
$title = $title ?? ($lang_functions['text_latest_comments'] ?? 'Latest Comments');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('latestcomments._latestcomments')
@endsection
