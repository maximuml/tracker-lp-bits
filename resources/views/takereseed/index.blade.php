@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/takereseed.head_reseed_request'))

@section('content')
<div class="text-center">{{ $message ?? (__('legacy/takereseed.std_it_worked')) }}</div>
@endsection
