@extends('layouts.app')

@section('title', \App\Support\Locale::trans('search.global_search', [], null))

@section('content')
<div class="nx-main nx-embedded w-[97%]">
@if ($vm->search === '')
    <h1 class="text-left">{{ __('search.global_search') }}</h1>
    <form class="nx-box flex flex-wrap items-end gap-3 text-left" action="/web/search" method="get" role="search">
        <div class="flex flex-col gap-1 grow min-w-[12rem]">
            <label for="nx-search-keyword">{{ __('search.search_keyword') }}</label>
            <input id="nx-search-keyword" type="search" name="search" required>
        </div>
        <div class="flex flex-col gap-1">
            <label for="nx-search-area">{{ __('search.search_area') }}</label>
            <select id="nx-search-area" name="search_area">
                @foreach (__('search.search_area_options') as $areaValue => $areaLabel)
                <option value="{{ $areaValue }}">{{ $areaLabel }}</option>
                @endforeach
            </select>
        </div>
        <button class="nx-btn nx-btn--primary" type="submit">{{ __('search.global_search') }}</button>
        <p class="w-full m-0">{{ __('search.text_empty_hint') }}</p>
    </form>
@else
    <h1 class="nx-sr-only">{{ __('search.global_search') }}</h1>
@endif
@if ($vm->hasResults)
    <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />
    @include('components.torrents.table', ['listVm' => $vm->listVm])
    <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
@elseif ($vm->search !== '')
    <x-std-message :text="__('torrents.std_try_again')" :htmlstrip="false"><x-slot:heading>{{ __('torrents.std_search_results_for') }}{{ \App\Support\Html\SafeHtml::fromUntrustedHtml($vm->searchstr_ori) }}"</x-slot></x-std-message>
@endif
</div>
@endsection
