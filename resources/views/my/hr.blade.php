@php
$pageTitle = ($userInfo->username ?? '') . ' - H&R';
@endphp
@extends('layouts.legacy')

@section('title', $pageTitle)

@section('content')
@include('my._hr')
@endsection
