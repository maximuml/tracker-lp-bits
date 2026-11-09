@extends('layouts.app', ['chromeVariant' => 'modern'])

@section('title', $title ?? __('forums.head_forums'))

@section('content')
@if ($action === 'newtopic' || $action === 'reply' || $action === 'quotepost' || $action === 'editpost')
<h1 class="nx-sr-only">{{ $title ?? __('forums.head_forums') }}</h1>
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
