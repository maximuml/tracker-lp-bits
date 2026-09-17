@extends('layouts.modern')

@section('title', $title ?? $lang_index['head_home'] ?? 'Home')

@section('content')
@include('index.sections.news')
@if(!empty($extraModules))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($extraModules ?? '')))
@endif
@include('index.sections.shoutbox')
@include('index.sections.forum_posts')
@if($latestTorrents['show'])
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($latestTorrents['html'] ?? '')))
@endif
@include('index.sections.top_uploaders')
@include('index.sections.polls')
@include('index.sections.stats')
@include('index.sections.tracker_load')
@include('index.sections.disclaimer')
@include('index.sections.browser_note')
@endsection
