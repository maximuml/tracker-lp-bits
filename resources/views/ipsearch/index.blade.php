@php
$lang_ipsearch = (array) (\App\Support\SupportContext::getGlobal('lang_ipsearch') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_ipsearch['head_search_ip_history'] ?? 'Search IP History')

@section('content')
@include('ipsearch._ipsearch')
@endsection
