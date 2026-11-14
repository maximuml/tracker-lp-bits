@extends('layouts.app')

@section('title', $title ?? (__('offers.head_offer')))

@section('content')
<h1 class="nx-sr-only">{{ $title ?? __('offers.head_offer') }}</h1>
@if ($vm->action === 'add_offer')
@include('offers.sections.add_offer', ['add_offer' => $vm->add_offer])
@elseif ($vm->action === 'off_details')
@include('offers.sections.off_details', ['off_details' => $vm->off_details])
@elseif ($vm->action === 'edit_offer')
@include('offers.sections.edit_offer', ['edit_offer' => $vm->edit_offer])
@elseif ($vm->action === 'offer_vote')
@include('offers.sections.offer_vote', ['offer_vote' => $vm->offer_vote])
@else
@include('offers.sections.list', ['list' => $vm->list])
@endif
@endsection
