@extends('layouts.legacy')

@section('title', (''))

@section('content')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($smiliesFrame ?? ''))
@endsection
