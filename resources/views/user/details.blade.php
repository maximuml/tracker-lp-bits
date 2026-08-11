@php
$title = $lang['head_details_for'] . $user['username'];
@endphp

@extends('layouts.legacy')

@section('content')
@include('user._details')
@endsection
