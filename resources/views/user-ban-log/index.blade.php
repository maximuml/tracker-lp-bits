@extends('layouts.nexus_legacy')

@section('title', config('app.name'))

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('user-ban-log._user-ban-log_legacy')
@endsection
