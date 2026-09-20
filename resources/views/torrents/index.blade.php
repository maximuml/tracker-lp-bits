@extends('layouts.modern')

@section('title', $pageTitle)

@section('content')
    @if (($inclbookmarked ?? 0) === 0)<h1 class="nx-sr-only">{{ $pageTitle }}</h1>@endif
    @include('torrents._torrents')
@endsection
