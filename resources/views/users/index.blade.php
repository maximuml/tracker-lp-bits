@php
$lang_users = (array) (\App\Support\SupportContext::getGlobal('lang_users') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_users['head_users'] ?? 'Users')

@section('content')
@include('users._users')
@endsection
