@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('ipcheck._ipcheck')
@endsection
