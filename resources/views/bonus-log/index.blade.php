@extends('layouts.legacy')

@section('title', $title ?? 'Bonus log')

@section('content')
@include('bonus-log._bonus-log')
@endsection
