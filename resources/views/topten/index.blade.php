@php
$lang_topten = (array) (\app(\App\Support\Globals::class)->get('lang_topten') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_topten['head_top_ten'] ?? 'Top 10')

@section('content')
@include('topten._topten')
@endsection
