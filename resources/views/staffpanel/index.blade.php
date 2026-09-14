
@extends('layouts.legacy')

@section('title', $lang_staffpanel['Administration'] ?? 'Administration')

@section('content')
@include('staffpanel._staffpanel')
@endsection
