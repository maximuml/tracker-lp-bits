@extends('layouts.legacy')

@section('title', $heading)

@section('content')
@php
echo \App\Support\Frame::stdMessage($heading, $message, false);
@endphp
@endsection
