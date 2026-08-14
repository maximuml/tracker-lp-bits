@php
$lang_attendance = (array) (\App\Support\SupportContext::getGlobal('lang_attendance') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_attendance['title'])

@section('content')
@include('attendance._attendance')
@endsection
