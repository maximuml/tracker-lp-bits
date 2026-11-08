@extends('layouts.app')

@section('title', $title ?? (__('polloverview.head_poll_overview')))

@section('content')
@if ($mode === 'detail')
<x-page :title="__('polloverview.text_polls_overview')" :subtitle="($poll['question'] ?? '')" width="list">

    <x-data-table :caption="__('polloverview.text_polls_overview')" captionHidden><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_id')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_added')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_question')}}</nobr></th></tr></thead></x-slot:head>

    <tr><td class="text-center"><a href="/web/polloverview?id={{ (int) ($poll['id'] ?? 0) }}">{{ (int) ($poll['id'] ?? 0) }}</a></td><td>{{ $pollAdded ?? '' }}</td><td><a href="/web/polloverview?id={{ (int) ($poll['id'] ?? 0) }}">{{ $poll['question'] ?? '' }}</a></td></tr>
    </x-data-table>

    <h2 class="text-center">{{ __('polloverview.text_poll_question')}}</h2><br />
    <x-data-table :caption="__('polloverview.text_poll_question')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_option_no')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_options')}}</th></tr></thead></x-slot:head>
    @foreach ($pollOptions as $pollOption)
        <tr><td>{{ $pollOption['index'] }}</td><td>{{ $pollOption['text'] }}</td></tr>
    @endforeach
    </x-data-table>

    <h2 class="text-center">{{ __('polloverview.text_polls_user_overview')}}</h2>

    @if ($count == 0)
        <p class="text-center">{{ __('polloverview.text_no_users_voted')}}</p>
    @else
        {{ $pagertop ?? '' }}
        <x-data-table :caption="__('polloverview.text_polls_user_overview')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_username')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_selection')}}<nobr></th></tr></thead></x-slot:head>
        @foreach ($answers as $answerRow)
            <tr><td>{{ $answerRow['usernameHtml'] ?? '' }}</td><td>{{ $poll["option{$answerRow['selection']}"] ?? '' }}</td></tr>
        @endforeach
        </x-data-table>
        {{ $pagerbottom ?? '' }}
    @endif
</x-page>

@else
<x-page :title="__('polloverview.text_polls_overview')" width="list">

    <x-data-table :caption="__('polloverview.text_polls_overview')" captionHidden><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_id')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('polloverview.col_added')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('polloverview.col_question')}}</nobr></th></tr></thead></x-slot:head>
    @foreach ($polls as $pollRow)
        <tr><td class="text-center"><a href="/web/polloverview?id={{ $pollRow['id'] }}">{{ $pollRow['id'] }}</a></td><td>{{ $pollRow['addedHtml'] ?? '' }}</td><td><a href="/web/polloverview?id={{ $pollRow['id'] }}">{{ $pollRow['question'] }}</a></td></tr>
    @endforeach
    </x-data-table>
</x-page>
@endif
@endsection
