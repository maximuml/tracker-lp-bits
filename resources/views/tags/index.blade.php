@php
$lang_tags = (array) (\App\Support\SupportContext::getGlobal('lang_tags') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_tags['head_tags'])

@section('content')
@include('tags._tags')
@endsection
