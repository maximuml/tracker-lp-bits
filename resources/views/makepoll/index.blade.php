@php
$lang_makepoll = (array) (\App\Support\SupportContext::getGlobal('lang_makepoll') ?? []);
$pollid = (int) (\App\Support\SupportContext::getQuery('pollid') ?? 0);
$title = $title ?? ($pollid > 0
    ? ($lang_makepoll['head_edit_poll'] ?? 'Edit poll')
    : ($lang_makepoll['head_new_poll'] ?? 'New poll'));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('makepoll._makepoll')
@endsection
