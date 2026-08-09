@php
$title = $lang['head_details_for'] . $user['username'];
@endphp

@extends('layouts.nexus_legacy')

@section('title', $title . ' :: ' . ($siteName ?? config('app.name')))

@section('content')
    @php
    $nexus_legacy_layout = true;
    @endphp
    @include('user._details_legacy')
@endsection
