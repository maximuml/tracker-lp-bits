@php
$lang_usercp = (array) (\app(\App\Support\Globals::class)->get('lang_usercp') ?? []);
$title = $title ?? ($lang_usercp['head_control_panel'] ?? 'Control Panel');
$action = $action ?? '';
$type = $type ?? '';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($action === 'personal')
@include('usercp.sections.personal')
@elseif ($action === 'tracker')
@include('usercp.sections.tracker')
@elseif ($action === 'forum')
@include('usercp.sections.forum')
@elseif ($action === 'security')
@include('usercp.sections.security')
@else
@include('usercp.sections.home')
@endif
@endsection
