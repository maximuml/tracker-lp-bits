
@extends('layouts.legacy')

@section('title', $lang_ipsearch['head_search_ip_history'] ?? 'Search IP History')

@section('content')
@include('ipsearch._ipsearch')
@endsection
