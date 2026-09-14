
@extends('layouts.legacy')

@section('title', match ($mode ?? '') {
    'newsect' => 'Add section',
    'edit' => 'Edit rules',
    default => 'Rules Management',
})

@section('content')
@include('modrules._modrules')
@endsection
