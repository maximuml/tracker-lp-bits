@extends('layouts.app', ['shell' => 'bare'])

@section('title', $pageTitle)

@section('content')
    @include('bitbucket._upload')
@endsection
