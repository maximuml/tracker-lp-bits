@php
$title = $lang['head_details_for'] . $user['username'];
@endphp

@extends('layouts.legacy')

@section('content')
@php include resource_path('views/user/_details_legacy.php'); @endphp
@endsection
