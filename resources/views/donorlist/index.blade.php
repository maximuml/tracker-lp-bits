@extends('layouts.nexus_legacy')

@section('title', config('app.name'))

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('donorlist._donorlist_legacy')
@endsection
