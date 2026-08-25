@php
$lang_polloverview = (array) (\App\Support\SupportContext::getGlobal('lang_polloverview') ?? []);
$mode = (string) ($mode ?? 'list');
$poll = (array) ($poll ?? []);
$polls = (array) ($polls ?? []);
$answers = (array) ($answers ?? []);
$count = (int) ($count ?? 0);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$userDisplayMap = (array) ($userDisplayMap ?? []);
$title = $title ?? ($lang_polloverview['head_poll_overview'] ?? 'Poll overview');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($mode === 'detail')
    @php $pollid = (int) ($poll['id'] ?? 0); @endphp
    <h1 align="center">{{ $lang_polloverview['text_polls_overview'] ?? 'Polls overview' }}</h1>

    <table width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ $lang_polloverview['col_id'] ?? 'ID' }}</nobr></td><td class=colhead><nobr>{{ $lang_polloverview['col_added'] ?? 'Added' }}</nobr></td><td class=colhead><nobr>{{ $lang_polloverview['col_question'] ?? 'Question' }}</nobr></td></tr>

    @php $added = \App\Support\Time::format($poll['added'] ?? ''); @endphp
    <tr><td align=center><a href="polloverview.php?id={{ $pollid }}">{{ $pollid }}</a></td><td>{{ $added }}</td><td><a href="polloverview.php?id={{ $pollid }}">{{ $poll['question'] ?? '' }}</a></td></tr>
    </table>

    <h1 align="center">{{ $lang_polloverview['text_poll_question'] ?? 'Poll question' }}</h1><br />
    <table width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>{{ $lang_polloverview['col_option_no'] ?? 'Option #' }}</td><td class=colhead>{{ $lang_polloverview['col_options'] ?? 'Options' }}</td></tr>
    @for ($i = 0; $i < 20; $i++)
        @php $option = (string) ($poll["option{$i}"] ?? ''); @endphp
        @if ($option !== '')
            <tr><td>{{ $i }}</td><td>{{ $option }}</td></tr>
        @endif
    @endfor
    </table>

    <h1 align="center">{{ $lang_polloverview['text_polls_user_overview'] ?? 'Users voted' }}</h1>

    @if ($count == 0)
        <p align="center">{{ $lang_polloverview['text_no_users_voted'] ?? 'No users voted.' }}</p>
    @else
        {{ $pagertop }}
        <table width=737 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><nobr>{{ $lang_polloverview['col_username'] ?? 'Username' }}</nobr></td><td class=colhead align=center><nobr>{{ $lang_polloverview['col_selection'] ?? 'Selection' }}<nobr></td></tr>
        @foreach ($answers as $answerRow)
            @php
                $useras = (array) $answerRow;
                $uid = (int) ($useras['userid'] ?? 0);
                $selection = (int) ($useras['selection'] ?? 0);
                $username = $userDisplayMap[$uid] ?? \App\Support\UserDisplay::username($uid);
            @endphp
            <tr><td>{!! $username !!}</td><td>{{ $poll["option{$selection}"] ?? '' }}</td></tr>
        @endforeach
        </table>
        {{ $pagerbottom }}
    @endif

@else
    @if (empty($polls))
        @php \App\Support\LegacyResponse::abort($lang_polloverview['std_error'] ?? 'Error', $lang_polloverview['text_no_users_voted'] ?? 'No polls found.'); @endphp
    @endif
    <h1 align="center">{{ $lang_polloverview['text_polls_overview'] ?? 'Polls overview' }}</h1>

    <table width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ $lang_polloverview['col_id'] ?? 'ID' }}</nobr></td><td class=colhead>{{ $lang_polloverview['col_added'] ?? 'Added' }}</td><td class=colhead><nobr>{{ $lang_polloverview['col_question'] ?? 'Question' }}</nobr></td></tr>
    @foreach ($polls as $pollRow)
        @php
            $poll = (array) $pollRow;
            $added = \App\Support\Time::format($poll['added'] ?? '');
        @endphp
        <tr><td align=center><a href="polloverview.php?id={{ $poll['id'] }}">{{ $poll['id'] }}</a></td><td>{{ $added }}</td><td><a href="polloverview.php?id={{ $poll['id'] }}">{{ $poll['question'] }}</a></td></tr>
    @endforeach
    </table>
@endif
@endsection
