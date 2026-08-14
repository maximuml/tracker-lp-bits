@php
$lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
$title = $title ?? ($lang_usercp['head_control_panel'] ?? 'Control Panel');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('usercp._usercp')
@endsection
