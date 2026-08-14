@php
$title = match ($mode ?? '') {
    'newsect' => 'Add section',
    'edit' => 'Edit rules',
    default => 'Rules Management',
};
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('modrules._modrules')
@endsection
