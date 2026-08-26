@php
$lang_uploaders = (array) (\app(\App\Support\Globals::class)->get('lang_uploaders') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_uploaders['head_uploaders'] ?? 'Uploaders')

@section('content')
@include('uploaders._uploaders')
@endsection
