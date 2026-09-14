@extends('layouts.legacy')

@section('title', \App\Support\Locale::trans('search.global_search', [], null))

@section('content')
@include('search._search')
@endsection
