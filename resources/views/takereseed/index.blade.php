@extends('layouts.app')

@section('title', __('takereseed.head_reseed_request'))

@section('content')
<div class="text-center">{{ $vm->message ?? (__('takereseed.std_it_worked')) }}</div>
@endsection
