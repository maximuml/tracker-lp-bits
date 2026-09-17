@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/polloverview.head_poll_overview')))

@section('content')
@if ($mode === 'detail')
    <h1 align="center">{{ __('legacy/polloverview.text_polls_overview')}}</h1>

    <table data-nx="data" width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ __('legacy/polloverview.col_id')}}</nobr></td><td class=colhead><nobr>{{ __('legacy/polloverview.col_added')}}</nobr></td><td class=colhead><nobr>{{ __('legacy/polloverview.col_question')}}</nobr></td></tr>

    <tr><td align=center><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ (int) ($poll['id'] ?? 0) }}</a></td><td>{{ $pollAdded ?? '' }}</td><td><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ $poll['question'] ?? '' }}</a></td></tr>
    </table>

    <h1 align="center">{{ __('legacy/polloverview.text_poll_question')}}</h1><br />
    <table data-nx="data" width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>{{ __('legacy/polloverview.col_option_no')}}</td><td class=colhead>{{ __('legacy/polloverview.col_options')}}</td></tr>
    @foreach ($pollOptions as $pollOption)
        <tr><td>{{ $pollOption['index'] }}</td><td>{{ $pollOption['text'] }}</td></tr>
    @endforeach
    </table>

    <h1 align="center">{{ __('legacy/polloverview.text_polls_user_overview')}}</h1>

    @if ($count == 0)
        <p align="center">{{ __('legacy/polloverview.text_no_users_voted')}}</p>
    @else
        {{ $pagertop ?? '' }}
        <table data-nx="data" width=737 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><nobr>{{ __('legacy/polloverview.col_username')}}</nobr></td><td class=colhead align=center><nobr>{{ __('legacy/polloverview.col_selection')}}<nobr></td></tr>
        @foreach ($answers as $answerRow)
            <tr><td>{{ $answerRow['usernameHtml'] ?? '' }}</td><td>{{ $poll["option{$answerRow['selection']}"] ?? '' }}</td></tr>
        @endforeach
        </table>
        {{ $pagerbottom ?? '' }}
    @endif

@else
    <h1 align="center">{{ __('legacy/polloverview.text_polls_overview')}}</h1>

    <table data-nx="data" width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ __('legacy/polloverview.col_id')}}</nobr></td><td class=colhead>{{ __('legacy/polloverview.col_added')}}</td><td class=colhead><nobr>{{ __('legacy/polloverview.col_question')}}</nobr></td></tr>
    @foreach ($polls as $pollRow)
        <tr><td align=center><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['id'] }}</a></td><td>{{ $pollRow['addedHtml'] ?? '' }}</td><td><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['question'] }}</a></td></tr>
    @endforeach
    </table>
@endif
@endsection
