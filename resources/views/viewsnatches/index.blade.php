@php
$lang_viewsnatches = (array) (\App\Support\SupportContext::getGlobal('lang_viewsnatches') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_viewsnatches['head_snatch_detail'])

@section('content')
@include('viewsnatches._viewsnatches')
@endsection
