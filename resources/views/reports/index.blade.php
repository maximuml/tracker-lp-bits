@php
$lang_reports = (array) (\app(\App\Support\Globals::class)->get('lang_reports') ?? []);
@endphp
@extends('layouts.legacy')

@section('title', $lang_reports['head_reports'] ?? 'Reports')

@section('content')
@include('reports._reports')
@endsection
