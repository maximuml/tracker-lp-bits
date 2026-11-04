@extends('layouts.app')

@section('title', __('staff.head_staff'))

@section('content')
<h1 class="nx-sr-only">{{ __('staff.head_staff') }}</h1>
@include('components.staff.staff')
@endsection
