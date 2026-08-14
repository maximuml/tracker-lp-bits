@php
$title = match ($action ?? '') {
    'edit' => 'Max. Login Attempts - EDIT (' . htmlspecialchars((string) ($editRow['id'] ?? '')) . ')',
    'searchip' => 'Max. Login Attempts - Search',
    default => 'Max. Login Attempts - Show List',
};
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('maxlogin._maxlogin')
@endsection
