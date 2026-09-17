@extends('layouts.modern')

@section('title', $title ?? __('legacy/index.head_home'))

@section('content')
@include('index.sections.news')
@if(!empty($extraModules))
{{ ($extraModules ?? '') }}
@endif
@include('index.sections.shoutbox')
@include('index.sections.forum_posts')
@if($latestTorrents['show'])
{{ ($latestTorrents['html'] ?? '') }}
@endif
@include('index.sections.top_uploaders')
@include('index.sections.polls')
@include('index.sections.stats')
@include('index.sections.tracker_load')
@include('index.sections.disclaimer')
@include('index.sections.browser_note')
@endsection
