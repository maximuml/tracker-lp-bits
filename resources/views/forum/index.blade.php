@extends('layouts.app', ['chromeVariant' => 'modern'])

@section('title', $title ?? __('forums.head_forums'))

@section('content')
@if ($vm->action === 'newtopic' || $vm->action === 'reply' || $vm->action === 'quotepost' || $vm->action === 'editpost')
<h1 class="nx-sr-only">{{ $title ?? __('forums.head_forums') }}</h1>
@include('forum.sections.compose', ['compose' => $vm->compose])
@elseif ($vm->action === 'viewtopic')
@include('forum.sections.viewtopic', ['viewtopic' => $vm->viewtopic])
@elseif ($vm->action === 'viewforum')
@include('forum.sections.viewforum', ['viewforum' => $vm->viewforum])
@elseif ($vm->action === 'viewunread')
@include('forum.sections.viewunread', ['viewunread' => $vm->viewunread])
@elseif ($vm->action === 'search')
@include('forum.sections.search', ['search' => $vm->search])
@else
@include('forum.sections.forums', ['forums' => $vm->forums])
@endif
@endsection
