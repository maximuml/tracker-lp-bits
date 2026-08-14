@php
$title = 'BitBucket Log';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('bitbucketlog._bitbucketlog')
@endsection
