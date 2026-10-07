@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', \App\Support\Locale::trans('search.global_search', [], null))

@section('content')
<div class="nx-main nx-embedded w-[97%]">
@if (! empty($hasResults))
    {{ $pagertop ?? '' }}
    @include('torrents._table')
    {{ $pagerbottom ?? '' }}
@elseif (($search ?? '') !== '')
    <x-std-message :text="__('torrents.std_try_again')" :htmlstrip="false"><x-slot:heading>{{ __('torrents.std_search_results_for') }}{{ \App\Support\Html\SafeHtml::fromUntrustedHtml($searchstr_ori ?? '') }}"</x-slot></x-std-message>
@endif
</div>
@endsection
