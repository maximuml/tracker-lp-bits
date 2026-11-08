@extends('layouts.app')

@section('title', __('staff.head_staff'))

@section('content')
<x-page :title="__('staff.head_staff')" width="list">
@include('components.staff.staff')
</x-page>
@endsection
