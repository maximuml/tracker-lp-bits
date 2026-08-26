@php
$lang_users = (array) (\app(\App\Support\Globals::class)->get('lang_users') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_users['head_users'] ?? 'Users')

@section('content')
@include('users._users')
@endsection
