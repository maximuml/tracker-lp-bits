@extends('layouts.legacy')

@section('title', 'User ban log')

@section('content')
@php
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$query = \App\Models\UserBanLog::query();
$q = htmlspecialchars(\App\Support\SupportContext::getRequestInput('q') ?? '');
if (! empty($q)) {
    $query->where('username', 'like', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 50;
[$paginationTop, $paginationBottom, $limit, $offset] = \App\Support\Pagination::pager($perPage, $total, '?');
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get()->toArray();
$header = [
    'id' => 'ID',
    'uid' => 'UID',
    'username' => 'Username',
    'reason' => 'Reason',
    'created_at' => 'Created at',
];
$table = \App\Support\Html::buildTable($header, $rows);
$q = htmlspecialchars($q);
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
