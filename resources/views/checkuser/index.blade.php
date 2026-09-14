
@extends('layouts.legacy')

@section('title', $lang_checkuser['head_detail_for'] . $user['username'])

@section('content')
@include('checkuser._checkuser')
@endsection
