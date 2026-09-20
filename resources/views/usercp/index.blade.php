
@extends('layouts.modern')

@section('title', $title ?? (__('legacy/usercp.head_control_panel')))

@section('content')
<h1 class="nx-sr-only">{{ $title ?? __('legacy/usercp.head_control_panel') }}</h1>
@if ($errors->any())
<x-alert type="error" :title="__('legacy/functions.std_error')">
    @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
</x-alert>
@endif
@if (($action ?? '') === 'personal')
@include('usercp.sections.personal')
@elseif (($action ?? '') === 'tracker')
@include('usercp.sections.tracker')
@elseif (($action ?? '') === 'forum')
@include('usercp.sections.forum')
@elseif (($action ?? '') === 'security')
@include('usercp.sections.security')
@else
@include('usercp.sections.home')
@endif
@endsection
