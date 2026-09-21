@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $headTitle)

@section('content')
@include('comments._form')
@endsection
