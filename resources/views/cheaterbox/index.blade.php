@php
$lang_cheaterbox = (array) (\app(\App\Support\Globals::class)->get('lang_cheaterbox') ?? []);
@endphp
@extends('layouts.legacy')

@section('title', $lang_cheaterbox['head_cheaterbox'] ?? 'Cheaterbox')

@section('content')
@include('cheaterbox._cheaterbox')
@endsection
