@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('legacy/polloverview.head_poll_overview')))

@section('content')
@if ($mode === 'detail')
    <h1 class="text-center">{{ __('legacy/polloverview.text_polls_overview')}}</h1>

    <table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/polloverview.text_polls_overview')}}</caption><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_id')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_added')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_question')}}</nobr></th></tr>

    <tr><td class="text-center"><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ (int) ($poll['id'] ?? 0) }}</a></td><td>{{ $pollAdded ?? '' }}</td><td><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ $poll['question'] ?? '' }}</a></td></tr>
    </table>

    <h1 class="text-center">{{ __('legacy/polloverview.text_poll_question')}}</h1><br />
    <table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/polloverview.text_poll_question')}}</caption><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/polloverview.col_option_no')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/polloverview.col_options')}}</th></tr>
    @foreach ($pollOptions as $pollOption)
        <tr><td>{{ $pollOption['index'] }}</td><td>{{ $pollOption['text'] }}</td></tr>
    @endforeach
    </table>

    <h1 class="text-center">{{ __('legacy/polloverview.text_polls_user_overview')}}</h1>

    @if ($count == 0)
        <p class="text-center">{{ __('legacy/polloverview.text_no_users_voted')}}</p>
    @else
        {{ $pagertop ?? '' }}
        <table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/polloverview.text_polls_user_overview')}}</caption>
        <tr><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_username')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_selection')}}<nobr></th></tr>
        @foreach ($answers as $answerRow)
            <tr><td>{{ $answerRow['usernameHtml'] ?? '' }}</td><td>{{ $poll["option{$answerRow['selection']}"] ?? '' }}</td></tr>
        @endforeach
        </table>
        {{ $pagerbottom ?? '' }}
    @endif

@else
    <h1 class="text-center">{{ __('legacy/polloverview.text_polls_overview')}}</h1>

    <table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/polloverview.text_polls_overview')}}</caption><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_id')}}</nobr></th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/polloverview.col_added')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/polloverview.col_question')}}</nobr></th></tr>
    @foreach ($polls as $pollRow)
        <tr><td class="text-center"><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['id'] }}</a></td><td>{{ $pollRow['addedHtml'] ?? '' }}</td><td><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['question'] }}</a></td></tr>
    @endforeach
    </table>
@endif
@endsection
