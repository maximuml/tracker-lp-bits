@php
$lang_moforums = (array) (\App\Support\SupportContext::getGlobal('lang_moforums') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_moforums['head_overforum_management'] ?? 'Overforum management')

@section('content')
@include('moforums._moforums')
@endsection
