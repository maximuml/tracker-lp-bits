@php
$lang_index = (array) (\App\Support\SupportContext::getGlobal('lang_index') ?? []);
$CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$toastLang = json_encode([
    'newMessage' => $lang_index['toast_new_message'] ?? 'New message',
    'shoutboxMention' => $lang_index['toast_shoutbox_mention'] ?? 'Shoutbox mention',
    'from' => $lang_index['toast_from'] ?? 'From',
    'close' => $lang_index['toast_close'] ?? 'Close',
    'userId' => (int) ($CURUSER['id'] ?? 0),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
\Nexus\Nexus::css('styles/shoutbox.css', 'header', true);
\Nexus\Nexus::js('js/shoutbox.js', 'footer', true);
\Nexus\Nexus::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
\Nexus\Nexus::css('styles/toast.css', 'header', true);
\Nexus\Nexus::js('js/toast.js', 'footer', true);
@endphp
@extends('layouts.legacy')

@section('title', $title ?? $lang_index['head_home'] ?? 'Home')

@section('content')
@include('index.sections.news')
@if(!empty($extraModules))
{!! $extraModules !!}
@endif
@include('index.sections.shoutbox')
@include('index.sections.forum_posts')
@if($latestTorrents['show'])
{!! $latestTorrents['html'] !!}
@endif
@include('index.sections.top_uploaders')
@include('index.sections.polls')
@include('index.sections.stats')
@include('index.sections.tracker_load')
@include('index.sections.disclaimer')
@include('index.sections.browser_note')
@endsection
