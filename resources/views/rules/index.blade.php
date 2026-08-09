@extends('layouts.nexus_legacy')

@section('title', config('app.name'))

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('rules._rules_legacy')
@endsection
