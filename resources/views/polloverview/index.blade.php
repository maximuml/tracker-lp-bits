@extends('layouts.app')

@section('title', $vm->title ?? (__('polloverview.head_poll_overview')))

@section('content')
@if ($vm->mode === 'detail')
<x-page :title="__('polloverview.text_polls_overview')" :subtitle="($vm->poll['question'] ?? '')" width="list">

    <x-data-table :caption="__('polloverview.text_polls_overview')" captionHidden><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_id')}}</span></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_added')}}</span></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_question')}}</span></th></tr></thead></x-slot:head>

    <tr><td class="text-center"><a href="/web/polloverview?id={{ (int) ($vm->poll['id'] ?? 0) }}">{{ (int) ($vm->poll['id'] ?? 0) }}</a></td><td>{{ $vm->pollAdded ?? '' }}</td><td><a href="/web/polloverview?id={{ (int) ($vm->poll['id'] ?? 0) }}">{{ $vm->poll['question'] ?? '' }}</a></td></tr>
    </x-data-table>

    <h2 class="text-center">{{ __('polloverview.text_poll_question')}}</h2><br />
    <x-data-table :caption="__('polloverview.text_poll_question')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_option_no')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_options')}}</th></tr></thead></x-slot:head>
    @foreach ($vm->pollOptions as $pollOption)
        <tr><td>{{ $pollOption['index'] }}</td><td>{{ $pollOption['text'] }}</td></tr>
    @endforeach
    </x-data-table>

    <h2 class="text-center">{{ __('polloverview.text_polls_user_overview')}}</h2>

    @if ($vm->count == 0)
        <p class="text-center">{{ __('polloverview.text_no_users_voted')}}</p>
    @else
        @if ($vm->pager !== [])
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />
@endif
        <x-data-table :caption="__('polloverview.text_polls_user_overview')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_username')}}</span></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_selection')}}<span class="nx-nobr"></th></tr></thead></x-slot:head>
        @foreach ($vm->answers as $answerRow)
            <tr><td>{{ $answerRow['usernameHtml'] ?? '' }}</td><td>{{ $vm->poll["option{$answerRow['selection']}"] ?? '' }}</td></tr>
        @endforeach
        </x-data-table>
        @if ($vm->pager !== [])
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
@endif
    @endif
</x-page>

@else
<x-page :title="__('polloverview.text_polls_overview')" width="list">

    <x-data-table :caption="__('polloverview.text_polls_overview')" captionHidden><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_id')}}</span></th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_added')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('polloverview.col_question')}}</span></th></tr></thead></x-slot:head>
    @foreach ($vm->polls as $pollRow)
        <tr><td class="text-center"><a href="/web/polloverview?id={{ $pollRow['id'] }}">{{ $pollRow['id'] }}</a></td><td>{{ $pollRow['addedHtml'] ?? '' }}</td><td><a href="/web/polloverview?id={{ $pollRow['id'] }}">{{ $pollRow['question'] }}</a></td></tr>
    @endforeach
    </x-data-table>
</x-page>
@endif
@endsection
