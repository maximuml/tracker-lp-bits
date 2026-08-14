@php
$title = 'Clear cache';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<?php echo \App\Repositories\LegacyViewRepository::render('clearcache', get_defined_vars()); ?>
@endsection
