@php
$lang_delete = (array) (\App\Support\SupportContext::getGlobal('lang_delete') ?? []);
@endphp

@extends('layouts.legacy')

@section('title', $lang_delete['head_torrent_deleted'] ?? 'Torrent deleted')

@section('content')
@include('delete._delete')
@endsection
