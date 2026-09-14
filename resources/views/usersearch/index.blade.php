
@extends('layouts.legacy')

@section('title', $title ?? 'Administrative User Search')

@section('content')
@include('usersearch.sections.usersearch')
@endsection
