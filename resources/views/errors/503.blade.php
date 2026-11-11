@extends('layouts.app', ['chromeVariant' => 'modern'])
@section('title', __('errors.maintenance_title'))
@section('content')
    @include('errors._body', ['status' => '503', 'title' => __('errors.maintenance_title'), 'message' => __('errors.maintenance_message')])
@endsection
