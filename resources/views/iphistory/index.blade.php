@extends('layouts.legacy')

@section('title', $lang_iphistory['head_ip_history_log_for'].$username ?? 'IP History')

@section('content')
@include('iphistory._iphistory')
@endsection
