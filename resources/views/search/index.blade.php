@extends('layouts.nexus_legacy')

@section('title', config('app.name'))

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('search._search_legacy')
@endsection
