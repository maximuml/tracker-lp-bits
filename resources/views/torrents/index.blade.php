@extends('layouts.legacy_torrents')

@section('title', $pageTitle)

@section('content')
    @include('torrents._torrents')
@endsection
