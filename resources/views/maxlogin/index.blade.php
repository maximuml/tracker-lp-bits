
@extends('layouts.legacy')

@section('title', match ($action ?? '') {
    'edit' => 'Max. Login Attempts - EDIT (' . htmlspecialchars((string) ($editRow['id'] ?? '')) . ')',
    'searchip' => 'Max. Login Attempts - Search',
    default => 'Max. Login Attempts - Show List',
})

@section('content')
@include('maxlogin._maxlogin')
@endsection
