@php
$lang_getrss = (array) (\app(\App\Support\Globals::class)->get('lang_getrss') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_getrss['head_rss_feeds'])

@section('content')
@include('getrss._getrss')
@endsection
