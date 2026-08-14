@php
$lang_polloverview = (array) (\App\Support\SupportContext::getGlobal('lang_polloverview') ?? []);
$title = $title ?? ($lang_polloverview['head_poll_overview'] ?? 'Poll overview');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('polloverview._polloverview')
@endsection
