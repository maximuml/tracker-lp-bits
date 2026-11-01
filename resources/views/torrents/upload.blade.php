@extends('layouts.app', ['shell' => 'bare'])

@section('title', $pageTitle)

@section('content')
    <h1>{{ $pageTitle }}</h1>
    <p class="nx-upload__note">{{ __('upload.text_red_star_required') }}<span class="text-nxm-danger">*</span>{{ __('upload.text_red_star_required_end') }}</p>
    @include('components.torrents.upload')
@endsection
