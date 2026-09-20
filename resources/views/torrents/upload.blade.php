@extends('layouts.legacy_bare')

@section('title', $pageTitle)

@section('content')
    @include('torrents._upload')
@endsection
