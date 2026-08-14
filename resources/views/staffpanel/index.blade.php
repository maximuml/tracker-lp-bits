@php
$lang_staffpanel = (array) (\App\Support\SupportContext::getGlobal('lang_staffpanel') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_staffpanel['Administration'] ?? 'Administration')

@section('content')
@include('staffpanel._staffpanel')
@endsection
