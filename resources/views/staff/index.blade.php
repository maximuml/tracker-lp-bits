@php
$lang_staff = (array) (\App\Support\SupportContext::getGlobal('lang_staff') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_staff['head_staff'] ?? 'Staff')

@section('content')
@include('staff._staff')
@endsection
