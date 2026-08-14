@php
$lang_takereseed = (array) (\App\Support\SupportContext::getGlobal('lang_takereseed') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_takereseed['head_reseed_request'] ?? 'Reseed request')

@section('content')
@php
$lang_takereseed = (array) (\App\Support\SupportContext::getGlobal('lang_takereseed') ?? []);
$message = (string) ($message ?? $lang_takereseed['std_it_worked'] ?? 'Reseed request sent.');

print('<center>' . $message . '</center>');
@endphp

@endsection
