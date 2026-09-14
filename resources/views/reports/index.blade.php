@extends('layouts.legacy')

@section('title', $lang_reports['head_reports'] ?? 'Reports')

@section('content')
@include('reports._reports')
@endsection
