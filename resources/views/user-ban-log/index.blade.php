@extends('layouts.legacy')

@section('title', 'User ban log')

@section('content')
<div>
    <h1>User ban log</h1>
    <form id="filterForm" action="{{ $serverRequestUri }}" method="get">
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="username">
        <input type="submit">
        <input type="reset" class="js-filter-reset">
    </form>
</div>

{{ ($table ?? '') }}
{{ ($paginationBottom ?? '') }}
@endsection
