@extends('layouts.nexus_legacy')

@section('title', $lang_index['head_home'] ?? config('app.name'))

@push('head')
    <link rel="stylesheet" href="styles/shoutbox.css" type="text/css" />
    <link rel="stylesheet" href="styles/toast.css" type="text/css" />
@endpush

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('index._index_legacy')
@endsection
