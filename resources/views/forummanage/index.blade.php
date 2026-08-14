@php
$lang_forummanage = (array) (\App\Support\SupportContext::getGlobal('lang_forummanage') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_forummanage['head_forum_management'] ?? 'Forum management')

@section('content')
@include('forummanage._forummanage')
@endsection
