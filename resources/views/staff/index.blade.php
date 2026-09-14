@extends('layouts.legacy')

@section('title', $lang_staff['head_staff'] ?? 'Staff')

@section('content')
@include('staff._staff')
@endsection
