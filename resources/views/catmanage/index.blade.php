@php
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
$title = $title ?? ($lang_catmanage['head_category_management'] ?? 'Category management');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('catmanage._catmanage')
@endsection
