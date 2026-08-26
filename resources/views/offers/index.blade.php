@php
$lang_offers = (array) (\app(\App\Support\Globals::class)->get('lang_offers') ?? []);
$title = $title ?? ($lang_offers['head_offer'] ?? 'Offers');
$action = $action ?? 'list';
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($action === 'add_offer')
@include('offers.sections.add_offer')
@elseif ($action === 'off_details')
@include('offers.sections.off_details')
@elseif ($action === 'edit_offer')
@include('offers.sections.edit_offer')
@elseif ($action === 'offer_vote')
@include('offers.sections.offer_vote')
@else
@include('offers.sections.list')
@endif
@endsection
