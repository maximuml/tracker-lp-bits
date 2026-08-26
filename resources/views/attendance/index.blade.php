@php
$lang_attendance = (array) (\app(\App\Support\Globals::class)->get('lang_attendance') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_attendance['title'])

@section('content')
@include('attendance._attendance')
@endsection
