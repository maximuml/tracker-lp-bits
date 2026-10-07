@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('staff.head_staff'))

@section('content')
@include('staff._staff')
@endsection
