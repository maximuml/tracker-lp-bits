@extends('layouts.app', ['shell' => 'bare'])

@section('title', $pageTitle)

@section('content')
    @include('components.bitbucket.upload')
@endsection
