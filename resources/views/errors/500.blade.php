@extends('layouts.app', ['chromeVariant' => 'modern'])
@section('title', __('errors.server_error_title'))
@section('content')
    @include('errors._body', ['status' => '500', 'title' => __('errors.server_error_title'), 'message' => __('errors.server_error_message')])
@endsection
