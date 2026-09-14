
@extends('layouts.legacy')

@section('title', $lang_getrss['head_rss_feeds'])

@section('content')
@include('getrss._getrss')
@endsection
