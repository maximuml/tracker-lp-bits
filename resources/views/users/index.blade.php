
@extends('layouts.legacy')

@section('title', $lang_users['head_users'] ?? 'Users')

@section('content')
@include('users._users')
@endsection
