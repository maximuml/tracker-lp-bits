@php
$lang_complains = (array) (\App\Support\SupportContext::getGlobal('lang_complains') ?? []);
$title = $title ?? ($lang_complains['text_complain'] ?? 'Complain');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('complains._complains')
@endsection
