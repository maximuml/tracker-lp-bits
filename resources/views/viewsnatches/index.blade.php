@php
$lang_viewsnatches = (array) (\app(\App\Support\Globals::class)->get('lang_viewsnatches') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_viewsnatches['head_snatch_detail'])

@section('content')
@include('viewsnatches._viewsnatches')
@endsection
