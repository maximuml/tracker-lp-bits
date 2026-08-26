@php
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
$action = \App\Support\SupportContext::getPost('action') ?? \App\Support\SupportContext::getQuery('action') ?? 'dailylog';
$action = in_array($action, ['dailylog', 'chronicle', 'news', 'poll'], true) ? $action : 'dailylog';
$mode = (string) ($mode ?? $action);
$title = $title ?? match ($mode) {
    'chronicle' => $lang_log['head_chronicle'] ?? 'Chronicle',
    'news' => $lang_log['head_news'] ?? 'News log',
    'poll' => $lang_log['head_previous_polls'] ?? 'Previous polls',
    default => $lang_log['head_site_log'] ?? 'Site log',
};
$canPollManage = (bool) ($canPollManage ?? false);
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<div id="lognav"><ul id="logmenu" class="menu">
@foreach (['dailylog' => ($lang_log['text_daily_log'] ?? 'Daily log'), 'chronicle' => ($lang_log['text_chronicle'] ?? 'Chronicle'), 'news' => ($lang_log['text_news'] ?? 'News'), 'poll' => ($lang_log['text_poll'] ?? 'Poll')] as $a => $label)
    <li{{ $mode === $a ? ' class=selected' : '' }}><a href="?action={{ $a }}">{{ $label }}</a></li>
@endforeach
</ul></div>

@if ($mode === 'dailylog')
    @php
        $q = (string) ($q ?? '');
        $search = (string) ($search ?? '');
        $canConfidentialLog = (bool) ($canConfidentialLog ?? false);
        $logRows = (array) ($logRows ?? []);
        $pagertop = (string) ($pagertop ?? '');
        $pagerbottom = (string) ($pagerbottom ?? '');
        $userDisplayMap = (array) ($userDisplayMap ?? []);
        $opts = ['all' => ($lang_log['text_all'] ?? 'All'), 'normal' => ($lang_log['text_normal'] ?? 'Normal'), 'mod' => ($lang_log['text_mod'] ?? 'Mod')];
    @endphp
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_log'] ?? 'Search log' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                @if ($canConfidentialLog)
                    {{ $lang_log['text_in'] ?? 'in' }}<select name="search">
                    @foreach ($opts as $value => $text)
                        <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                    @endforeach
                    </select>
                @endif
                <input type="hidden" name="action" value="dailylog">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if (empty($logRows))
        {{ $lang_log['text_log_empty'] ?? 'Log is empty.' }}
    @else
        <table width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><img class="time" src="pic/trans.gif" alt="time" title="{{ $lang_log['title_time_added'] ?? 'Time added' }}" /></td><td class=colhead align=left>{{ $lang_log['col_event'] ?? 'Event' }}
        @if ($canConfidentialLog)
            <td class=colhead align=left>{{ $lang_log['col_user'] ?? 'User' }}</td>
        @endif
        </td></tr>
        @foreach ($logRows as $arr)
            @php
                $color = '';
                $txt = (string) ($arr['txt'] ?? '');
                if (strpos($txt, 'was uploaded by') !== false) { $color = 'green'; }
                if (strpos($txt, 'was deleted by') !== false) { $color = 'red'; }
                if (strpos($txt, 'was added to the Request section') !== false) { $color = 'purple'; }
                if (strpos($txt, 'was edited by') !== false) { $color = 'blue'; }
                if (strpos($txt, 'settings updated by') !== false) { $color = 'darkred'; }
            @endphp
            <tr><td class="rowfollow nowrap" align=center>{{ \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false) }}</td><td class=rowfollow align=left><font color='{{ $color }}'>{{ htmlspecialchars($txt) }}</font></td>
            @if ($canConfidentialLog)
                @php $uid = (int) ($arr['uid'] ?? 0); @endphp
                <td class=rowfollow align=left>{{ $uid > 0 ? ($userDisplayMap[$uid] ?? \App\Support\UserDisplay::username($uid)) : 'System' }}</td>
            @endif
            </tr>
        @endforeach
        </table>
        {{ $pagerbottom }}
    @endif
    {{ $lang_log['time_zone_note'] ?? '' }}

@elseif ($mode === 'chronicle')
    @php
        $q = (string) ($q ?? '');
        $canManage = (bool) ($canManage ?? false);
        $chronicleRows = (array) ($chronicleRows ?? []);
        $editItem = (array) ($editItem ?? []);
        $pagertop = (string) ($pagertop ?? '');
        $pagerbottom = (string) ($pagerbottom ?? '');
    @endphp
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_chronicle'] ?? 'Search chronicle' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                <input type="hidden" name="action" value="chronicle">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if ($canManage)
        @php
            $cTitle = $lang_log['text_add_chronicle'] ?? 'Add chronicle';
            $value = $cTitle;
            $do = 'add';
            $editId = '';
            if (! empty($editItem)) {
                $cTitle = $lang_log['text_edit_chronicle'] ?? 'Edit chronicle';
                $value = (string) ($editItem['txt'] ?? '');
                $do = 'update';
                $editId = '<input type="hidden" name="id" value="'.(int) ($editItem['id'] ?? 0).'">';
            }
        @endphp
        <table border=1 cellspacing=0 width=940 cellpadding=5>
            <tr><td class=colhead align=left>{{ $cTitle }}</td></tr>
            <tr><td class=toolbox align=left>
                <form method="post" action="">
                    <textarea name="txt" style="width:500px" rows="3">{{ htmlspecialchars($value) }}</textarea>
                    <input type="hidden" name="action" value="chronicle">
                    <input type="hidden" name="do" value="{{ $do }}">
                    {!! $editId !!}
                    <input type=submit value="{{ $lang_log['submit_add'] ?? 'Add' }}"></form>
            </td></tr>
        </table><br />
    @endif
    @if (empty($chronicleRows))
        {{ $lang_log['text_chronicle_empty'] ?? 'Chronicle is empty.' }}
    @else
        <table width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center>{{ $lang_log['col_date'] ?? 'Date' }}</td><td class=colhead align=left>{{ $lang_log['col_event'] ?? 'Event' }}</td>{{ $canManage ? '<td class=colhead align=center>'.($lang_log['col_modify'] ?? 'Modify').'</td>' : '' }}</tr>
        @foreach ($chronicleRows as $arr)
            @php $date = \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false); @endphp
            <tr><td class=rowfollow align=center><nobr>{{ $date }}</nobr></td><td class=rowfollow align=left>{!! \App\Support\Format::formatComment((string) ($arr['txt'] ?? ''), true, false, true) !!}</td>{{ $canManage ? '<td align=center nowrap><b><a href="?action=chronicle&do=edit&id='.(int) ($arr['id'] ?? 0).'">'.($lang_log['text_edit'] ?? 'Edit').'</a>&nbsp;|&nbsp;<a href="?action=chronicle&do=del&id='.(int) ($arr['id'] ?? 0).'"><font color=red>'.($lang_log['text_delete'] ?? 'Delete').'</font></a></b></td>' : '' }}</tr>
        @endforeach
        </table>
        {{ $pagerbottom }}
    @endif
    {{ $lang_log['time_zone_note'] ?? '' }}

@elseif ($mode === 'news')
    @php
        $q = (string) ($q ?? '');
        $search = (string) ($search ?? '');
        $newsRows = (array) ($newsRows ?? []);
        $pagertop = (string) ($pagertop ?? '');
        $pagerbottom = (string) ($pagerbottom ?? '');
        $opts = ['title' => ($lang_log['text_title'] ?? 'Title'), 'body' => ($lang_log['text_body'] ?? 'Body'), 'both' => ($lang_log['text_both'] ?? 'Both')];
    @endphp
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_news'] ?? 'Search news' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                {{ $lang_log['text_in'] ?? 'in' }}<select name="search">
                @foreach ($opts as $value => $text)
                    <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                @endforeach
                </select>
                <input type="hidden" name="action" value="news">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if (empty($newsRows))
        {{ $lang_log['text_news_empty'] ?? 'No news found.' }}
    @else
        @foreach ($newsRows as $arr)
            @php $date = \App\Support\Time::format((string) ($arr['added'] ?? ''), true, false); @endphp
            <table width=940 border=1 cellspacing=0 cellpadding=5>
            <tr><td class=rowhead width='10%'>{{ $lang_log['col_title'] ?? 'Title' }}</td><td class=rowfollow align=left>{{ htmlspecialchars((string) ($arr['title'] ?? '')) }}</td></tr><tr><td class=rowhead width='10%'>{{ $lang_log['col_date'] ?? 'Date' }}</td><td class=rowfollow align=left>{{ $date }}</td></tr><tr><td class=rowhead width='10%'>{{ $lang_log['col_body'] ?? 'Body' }}</td><td class=rowfollow align=left>{!! \App\Support\Format::formatComment((string) ($arr['body'] ?? ''), false, false, true) !!}</td></tr>
            </table><br />
        @endforeach
        {{ $pagerbottom }}
    @endif
    {{ $lang_log['time_zone_note'] ?? '' }}

@elseif ($mode === 'poll')
    @php $pollData = (array) ($pollData ?? []); @endphp
    <table border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=center>{{ $lang_log['text_previous_polls'] ?? 'Previous polls' }}</td></tr>
    @foreach ($pollData as $item)
        @php
            $poll = (array) ($item['poll'] ?? []);
            $added = (string) ($item['added'] ?? '');
            $totalVotes = (string) ($item['totalVotes'] ?? '0');
            $options = (array) ($item['options'] ?? []);
        @endphp
        <tr><td align=center>
        <p class=sub>{{ $added }}
        @if ($canPollManage)
            - [<a href="makepoll.php?action=edit&pollid={{ (int) ($poll['id'] ?? 0) }}"><b>{{ $lang_log['text_edit'] ?? 'Edit' }}</b></a>]
            - [<a href="?action=poll&do=delete&pollid={{ (int) ($poll['id'] ?? 0) }}"><b>{{ $lang_log['text_delete'] ?? 'Delete' }}</b></a>]
        @endif
        <a name="{{ (int) ($poll['id'] ?? 0) }}"></a></p>
        <table class=main border=1 cellspacing=0 cellpadding=5><tr><td class=text>
        <p align=center><b>{{ htmlspecialchars((string) ($poll['question'] ?? '')) }}</b></p>
        <table width=100% class=main border=0 cellspacing=0 cellpadding=0>
        @foreach ($options as $opt)
            <tr><td class=embedded>{{ htmlspecialchars((string) ($opt['text'] ?? '')) }}&nbsp;&nbsp;</td><td class="embedded nowrap"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="unsltbar" src="pic/trans.gif" style="width: {{ (int) ($opt['percent'] ?? 0) * 3 }}px" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ (int) ($opt['percent'] ?? 0) }}%</td></tr>
        @endforeach
        </table>
        <p align=center>{{ $lang_log['text_votes'] ?? 'Votes: ' }}{{ $totalVotes }}</p>
        </td></tr></table><br /><br />
        </td></tr>
    @endforeach
    </table>
    {{ $lang_log['time_zone_note'] ?? '' }}
@endif
@endsection
