@extends('layouts.app', ['chromeVariant' => 'modern'])
@section('title', __('errors.forbidden_title'))
@section('content')
    @include('errors._body', ['status' => '403', 'title' => __('errors.forbidden_title'), 'message' => __('errors.forbidden_message')])
@endsection
