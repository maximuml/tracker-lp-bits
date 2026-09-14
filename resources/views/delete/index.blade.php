
@extends('layouts.legacy')

@section('title', $lang_delete['head_torrent_deleted'] ?? 'Torrent deleted')

@section('content')
@include('delete._delete')
@endsection
