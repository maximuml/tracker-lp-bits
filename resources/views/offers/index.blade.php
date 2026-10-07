@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('offers.head_offer')))

@section('content')
<h1 class="nx-sr-only">{{ $title ?? __('offers.head_offer') }}</h1>
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
