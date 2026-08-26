@php
$lang_incrementbulk = (array) (\app(\App\Support\Globals::class)->get('lang_incrementbulk') ?? []);
$stdheadMsgalert = false;
@endphp

@extends('layouts.legacy')

@section('title', $lang_incrementbulk['page_title'])

@section('content')
@include('increment-bulk._increment-bulk')
@endsection
