@php
$lang_offers = (array) (\App\Support\SupportContext::getGlobal('lang_offers') ?? []);
$title = $title ?? ($lang_offers['head_offer'] ?? 'Offers');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('offers._offers')
@endsection
