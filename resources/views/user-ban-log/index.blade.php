@extends('layouts.legacy')

@section('title', 'User ban log')

@section('content')
@php
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$q = (string) ($q ?? '');
$table = (string) ($table ?? '');
$paginationBottom = (string) ($paginationBottom ?? '');
@endphp

<div>
    <h1 style="text-align: center">User ban log</h1>
    <form id="filterForm" action="{{ $__server_REQUEST_URI }}" method="get">
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>

{!! $table !!}
{!! $paginationBottom !!}
@endsection
