@php
$lang_topten = (array) (\App\Support\SupportContext::getGlobal('lang_topten') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_topten['head_top_ten'] ?? 'Top 10')

@section('content')
@include('topten._topten')
@endsection
