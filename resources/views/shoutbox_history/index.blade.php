@php
$lang_shoutbox = (array) (\App\Support\SupportContext::getGlobal('lang_shoutbox') ?? []);
\Nexus\Nexus::css('styles/shoutbox.css', 'header', true);
\Nexus\Nexus::js('js/shoutbox.js', 'footer', true);
$title = $title ?? ($lang_shoutbox['text_history_title'] ?? 'Shoutbox history');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('shoutbox_history._shoutbox_history')
@endsection
