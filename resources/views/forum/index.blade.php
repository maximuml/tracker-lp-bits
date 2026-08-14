@php
$lang_forums = (array) (\App\Support\SupportContext::getGlobal('lang_forums') ?? []);
$title = $title ?? ($lang_forums['head_forums'] ?? 'Forums');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('forum._forums')
@endsection
