@extends('layouts.nexus_legacy')

@section('title', $lang_usercp['head_control_panel'] ?? config('app.name'))

@section('content')
    @php
    $GLOBALS['nexus_legacy_layout'] = true;
    @endphp
    @include('usercp._usercp_legacy')
@endsection
