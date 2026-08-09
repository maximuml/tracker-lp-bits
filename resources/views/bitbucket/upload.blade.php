@extends('layouts.nexus_legacy')

@section('title', $pageTitle)

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('bitbucket._upload_legacy')
@endsection
