@php
$title = $title ?? 'Administrative User Search';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('usersearch._usersearch')
@endsection
