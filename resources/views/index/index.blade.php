@extends('layouts.app')

@section('title', $title ?? __('legacy/index.head_home'))

@section('content')
<h1 class="nx-sr-only">{{ __('legacy/index.head_home') }}</h1>
@include('index.sections.news')
@if(!empty($extraModules))
{{ ($extraModules ?? '') }}
@endif
@include('index.sections.shoutbox')
@include('index.sections.forum_posts')
@if($latestTorrents->show)
{{ $latestTorrents->html }}
@endif
@include('index.sections.top_uploaders')
@if($polls->show)
<livewire:index-poll />
@endif
@include('index.sections.stats')
@include('index.sections.disclaimer')
@include('index.sections.browser_note')
@endsection
