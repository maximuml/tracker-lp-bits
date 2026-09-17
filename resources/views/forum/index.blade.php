@extends('layouts.modern')

@section('title', $title ?? ($lang['head_forums'] ?? 'Forums'))

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
