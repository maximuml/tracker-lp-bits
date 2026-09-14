
@extends('layouts.legacy')

@section('title', ($userInfo->username ?? '') . ' - H&R')

@section('content')
@include('my._hr')
@endsection
