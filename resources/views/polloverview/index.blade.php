@extends('layouts.legacy')

@section('title', $title ?? ($lang['head_poll_overview'] ?? 'Poll overview'))

@section('content')
@if ($mode === 'detail')
    <h1 align="center">{{ $lang['text_polls_overview'] ?? 'Polls overview' }}</h1>

    <table width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ $lang['col_id'] ?? 'ID' }}</nobr></td><td class=colhead><nobr>{{ $lang['col_added'] ?? 'Added' }}</nobr></td><td class=colhead><nobr>{{ $lang['col_question'] ?? 'Question' }}</nobr></td></tr>

    <tr><td align=center><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ (int) ($poll['id'] ?? 0) }}</a></td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pollAdded ?? ''))</td><td><a href="polloverview.php?id={{ (int) ($poll['id'] ?? 0) }}">{{ $poll['question'] ?? '' }}</a></td></tr>
    </table>

    <h1 align="center">{{ $lang['text_poll_question'] ?? 'Poll question' }}</h1><br />
    <table width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>{{ $lang['col_option_no'] ?? 'Option #' }}</td><td class=colhead>{{ $lang['col_options'] ?? 'Options' }}</td></tr>
    @foreach ($pollOptions as $pollOption)
        <tr><td>{{ $pollOption['index'] }}</td><td>{{ $pollOption['text'] }}</td></tr>
    @endforeach
    </table>

    <h1 align="center">{{ $lang['text_polls_user_overview'] ?? 'Users voted' }}</h1>

    @if ($count == 0)
        <p align="center">{{ $lang['text_no_users_voted'] ?? 'No users voted.' }}</p>
    @else
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
        <table width=737 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><nobr>{{ $lang['col_username'] ?? 'Username' }}</nobr></td><td class=colhead align=center><nobr>{{ $lang['col_selection'] ?? 'Selection' }}<nobr></td></tr>
        @foreach ($answers as $answerRow)
            <tr><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($answerRow['usernameHtml'] ?? ''))</td><td>{{ $poll["option{$answerRow['selection']}"] ?? '' }}</td></tr>
        @endforeach
        </table>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif

@else
    <h1 align="center">{{ $lang['text_polls_overview'] ?? 'Polls overview' }}</h1>

    <table width=737 border=1 cellspacing=0 cellpadding=5><tr>
    <td class=colhead align=center><nobr>{{ $lang['col_id'] ?? 'ID' }}</nobr></td><td class=colhead>{{ $lang['col_added'] ?? 'Added' }}</td><td class=colhead><nobr>{{ $lang['col_question'] ?? 'Question' }}</nobr></td></tr>
    @foreach ($polls as $pollRow)
        <tr><td align=center><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['id'] }}</a></td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pollRow['addedHtml'] ?? ''))</td><td><a href="polloverview.php?id={{ $pollRow['id'] }}">{{ $pollRow['question'] }}</a></td></tr>
    @endforeach
    </table>
@endif
@endsection
