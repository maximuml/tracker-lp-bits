@php
$lang_staffpanel = (array) (\app(\App\Support\Globals::class)->get('lang_staffpanel') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_staffpanel['Administration'] ?? 'Administration')

@section('content')
@include('staffpanel._staffpanel')
@endsection
