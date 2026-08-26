@php
$lang_forums = $lang ?? (array) (\app(\App\Support\Globals::class)->get('lang_forums') ?? []);
$title = $title ?? ($lang_forums['head_forums'] ?? 'Forums');
$action = $action ?? 'forums';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($action === 'newtopic' || $action === 'reply' || $action === 'quotepost' || $action === 'editpost')
@include('forum.sections.compose')
@elseif ($action === 'viewtopic')
@include('forum.sections.viewtopic')
@elseif ($action === 'viewforum')
@include('forum.sections.viewforum')
@elseif ($action === 'viewunread')
@include('forum.sections.viewunread')
@elseif ($action === 'search')
@include('forum.sections.search')
@else
@include('forum.sections.forums')
@endif
@endsection
