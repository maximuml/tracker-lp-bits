@extends('layouts.app', ['shell' => 'bare'])

@section('title', $pageTitle)

@section('content')
    <h1 class="nx-sr-only">{{ $pageTitle }}</h1>
    @include('components.torrents.upload')
@endsection
