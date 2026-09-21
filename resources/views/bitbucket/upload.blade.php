@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', $pageTitle)

@section('content')
    @include('bitbucket._upload')
@endsection
