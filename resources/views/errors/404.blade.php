@extends('layouts.app', ['chromeVariant' => 'modern'])
@section('title', __('errors.not_found_title'))
@section('content')
    @include('errors._body', ['status' => '404', 'title' => __('errors.not_found_title'), 'message' => __('errors.not_found_message')])
@endsection
