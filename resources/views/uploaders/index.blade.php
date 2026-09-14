
@extends('layouts.legacy')

@section('title', $lang_uploaders['head_uploaders'] ?? 'Uploaders')

@section('content')
@include('uploaders._uploaders')
@endsection
