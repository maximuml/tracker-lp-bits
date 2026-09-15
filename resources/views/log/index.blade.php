@extends('layouts.legacy')

@section('title', $title ?? ($lang_log['head_site_log'] ?? 'Site log'))

@section('content')
<div id="lognav"><ul id="logmenu" class="menu">
@foreach (['dailylog' => ($lang_log['text_daily_log'] ?? 'Daily log'), 'chronicle' => ($lang_log['text_chronicle'] ?? 'Chronicle'), 'news' => ($lang_log['text_news'] ?? 'News'), 'poll' => ($lang_log['text_poll'] ?? 'Poll')] as $a => $label)
    <li{{ $mode === $a ? ' class=selected' : '' }}><a href="?action={{ $a }}">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($label))</a></li>
@endforeach
</ul></div>

@if ($mode === 'dailylog')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_log'] ?? 'Search log' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                @if ($canConfidentialLog)
                    {{ $lang_log['text_in'] ?? 'in' }}<select name="search">
                    @foreach (['all' => ($lang_log['text_all'] ?? 'All'), 'normal' => ($lang_log['text_normal'] ?? 'Normal'), 'mod' => ($lang_log['text_mod'] ?? 'Mod')] as $value => $text)
                        <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                    @endforeach
                    </select>
                @endif
                <input type="hidden" name="action" value="dailylog">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if (empty($logRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['text_log_empty'] ?? 'Log is empty.'))
    @else
        <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><img class="time" src="pic/trans.gif" alt="time" title="{{ $lang_log['title_time_added'] ?? 'Time added' }}" /></td><td class=colhead align=left>{{ $lang_log['col_event'] ?? 'Event' }}
        @if ($canConfidentialLog)
            <td class=colhead align=left>{{ $lang_log['col_user'] ?? 'User' }}</td>
        @endif
        </td></tr>
        @foreach ($logRows as $arr)
            <tr><td class="rowfollow nowrap" align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['dateHtml'] ?? ''))</td><td class=rowfollow align=left><font color='{{ $arr['color'] ?? '' }}'>{{ $arr['txt'] ?? '' }}</font></td>
            @if ($canConfidentialLog)
                <td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['usernameHtml'] ?? ''))</td>
            @endif
            </tr>
        @endforeach
        </table>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['time_zone_note'] ?? ''))

@elseif ($mode === 'chronicle')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_chronicle'] ?? 'Search chronicle' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                <input type="hidden" name="action" value="chronicle">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if ($canManage)
        <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
            <tr><td class=colhead align=left>{{ ! empty($editItem) ? ($lang_log['text_edit_chronicle'] ?? 'Edit chronicle') : ($lang_log['text_add_chronicle'] ?? 'Add chronicle') }}</td></tr>
            <tr><td class=toolbox align=left>
                <form method="post" action="">
                    <textarea name="txt" style="width:500px" rows="3">{{ ! empty($editItem) ? ($editItem['txt'] ?? '') : ($lang_log['text_add_chronicle'] ?? 'Add chronicle') }}</textarea>
                    <input type="hidden" name="action" value="chronicle">
                    <input type="hidden" name="do" value="{{ ! empty($editItem) ? 'update' : 'add' }}">
                    @if (! empty($editItem))
                        <input type="hidden" name="id" value="{{ (int) ($editItem['id'] ?? 0) }}">
                    @endif
                    <input type=submit value="{{ $lang_log['submit_add'] ?? 'Add' }}"></form>
            </td></tr>
        </table><br />
    @endif
    @if (empty($chronicleRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['text_chronicle_empty'] ?? 'Chronicle is empty.'))
    @else
        <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center>{{ $lang_log['col_date'] ?? 'Date' }}</td><td class=colhead align=left>{{ $lang_log['col_event'] ?? 'Event' }}</td>@if ($canManage)<td class=colhead align=center>{{ $lang_log['col_modify'] ?? 'Modify' }}</td>@endif</tr>
        @foreach ($chronicleRows as $arr)
            <tr><td class=rowfollow align=center><nobr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['dateHtml'] ?? ''))</nobr></td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['bodyHtml'] ?? ''))</td>@if ($canManage)<td align=center nowrap><b><a href="?action=chronicle&do=edit&id={{ (int) ($arr['id'] ?? 0) }}">{{ $lang_log['text_edit'] ?? 'Edit' }}</a>&nbsp;|&nbsp;<form method="post" action="?action=chronicle&do=del" class="nx-inline"><input type="hidden" name="id" value="{{ (int) ($arr['id'] ?? 0) }}"><button type="submit" class="nx-btn-link" style="color:red;font-weight:bold">{{ $lang_log['text_delete'] ?? 'Delete' }}</button></form></b></td>@endif</tr>
        @endforeach
        </table>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['time_zone_note'] ?? ''))

@elseif ($mode === 'news')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ $lang_log['text_search_news'] ?? 'Search news' }}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                {{ $lang_log['text_in'] ?? 'in' }}<select name="search">
                @foreach (['title' => ($lang_log['text_title'] ?? 'Title'), 'body' => ($lang_log['text_body'] ?? 'Body'), 'both' => ($lang_log['text_both'] ?? 'Both')] as $value => $text)
                    <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                @endforeach
                </select>
                <input type="hidden" name="action" value="news">
                &nbsp;&nbsp;<input type=submit value="{{ $lang_log['submit_search'] ?? 'Search' }}"></form>
        </td></tr>
    </table><br />
    @if (empty($newsRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['text_news_empty'] ?? 'No news found.'))
    @else
        @foreach ($newsRows as $arr)
            <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
            <tr><td class=rowhead width='10%'>{{ $lang_log['col_title'] ?? 'Title' }}</td><td class=rowfollow align=left>{{ $arr['title'] ?? '' }}</td></tr><tr><td class=rowhead width='10%'>{{ $lang_log['col_date'] ?? 'Date' }}</td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['dateHtml'] ?? ''))</td></tr><tr><td class=rowhead width='10%'>{{ $lang_log['col_body'] ?? 'Body' }}</td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['bodyHtml'] ?? ''))</td></tr>
            </table><br />
        @endforeach
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['time_zone_note'] ?? ''))

@elseif ($mode === 'poll')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=center>{{ $lang_log['text_previous_polls'] ?? 'Previous polls' }}</td></tr>
    @foreach ($pollData as $item)
        <tr><td align=center>
        <p class=sub>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['added'] ?? ''))
        @if ($canPollManage)
            - [<a href="makepoll.php?action=edit&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ $lang_log['text_edit'] ?? 'Edit' }}</b></a>]
            - [<a href="?action=poll&do=delete&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ $lang_log['text_delete'] ?? 'Delete' }}</b></a>]
        @endif
        <a name="{{ (int) ($item['poll']['id'] ?? 0) }}"></a></p>
        <div class="nx-main nx-box">
        <p align=center><b>{{ $item['poll']['question'] ?? '' }}</b></p>
        <div class="nx-main">
        @foreach ($item['options'] ?? [] as $opt)
            <div class="nx-row"><div class="nx-embedded">{{ $opt['text'] ?? '' }}&nbsp;&nbsp;</div><div class="nx-embedded nx-nowrap nx-grow"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="unsltbar" src="pic/trans.gif" style="width: {{ (int) ($opt['percent'] ?? 0) * 3 }}px" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ (int) ($opt['percent'] ?? 0) }}%</div></div>
        @endforeach
        </div>
        <p align=center>{{ $lang_log['text_votes'] ?? 'Votes: ' }}{{ $item['totalVotes'] ?? '0' }}</p>
        </div><br /><br />
        </td></tr>
    @endforeach
    </table>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_log['time_zone_note'] ?? ''))
@endif
@endsection
