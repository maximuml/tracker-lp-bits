@extends('layouts.legacy')

@section('title', $lang_cheaterbox['head_cheaterbox'] ?? 'Cheaterbox')

@section('content')
@include('cheaterbox._cheaterbox')
@endsection
