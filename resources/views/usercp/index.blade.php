
@extends('layouts.modern')

@section('title', $title ?? (__('legacy/usercp.head_control_panel')))

@section('content')
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
