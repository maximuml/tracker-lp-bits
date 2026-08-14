@php
$lang_downloadnotice = (array) (\App\Support\SupportContext::getGlobal('lang_downloadnotice') ?? []);
$title = $title ?? ($lang_downloadnotice['head_download_notice'] ?? 'Download Notice');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('downloadnotice._downloadnotice')
@endsection
