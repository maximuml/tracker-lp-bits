@php
$title = \App\Support\Locale::trans('search.global_search', [], null);
@endphp

@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('search._search')
@endsection
