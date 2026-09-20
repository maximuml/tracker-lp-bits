@extends('layouts.legacy_bare')

@section('title', $pageTitle)

@section('content')
    <h1 class="nx-sr-only">{{ $pageTitle }}</h1>
    @include('torrents._upload')
@endsection
