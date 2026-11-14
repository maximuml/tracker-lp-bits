@extends('layouts.app')

@section('title', __('staff.head_staff'))

@section('content')
<x-page :title="__('staff.head_staff')" width="list">
@include('components.staff.staff', ['supportRows' => $vm->supportRows, 'forumModRows' => $vm->forumModRows, 'staffRows' => $vm->staffRows, 'vipRows' => $vm->vipRows, 'siteName' => $vm->siteName])
</x-page>
@endsection
