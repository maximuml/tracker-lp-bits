@php
$lang_checkuser = (array) (\app(\App\Support\Globals::class)->get('lang_checkuser') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_checkuser['head_detail_for'] . $user['username'])

@section('content')
@include('checkuser._checkuser')
@endsection
