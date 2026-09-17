@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/log.head_site_log')))

@section('content')
<div id="lognav"><ul id="logmenu" class="menu">
@foreach (['dailylog' => (__('legacy/log.text_daily_log')), 'chronicle' => (__('legacy/log.text_chronicle')), 'news' => (__('legacy/log.text_news')), 'poll' => (__('legacy/log.text_poll'))] as $a => $label)
    <li{{ $mode === $a ? ' class=selected' : '' }}><a href="?action={{ $a }}">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($label))</a></li>
@endforeach
</ul></div>

@if ($mode === 'dailylog')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ __('legacy/log.text_search_log')}}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                @if ($canConfidentialLog)
                    {{ __('legacy/log.text_in')}}<select name="search">
                    @foreach (['all' => (__('legacy/log.text_all')), 'normal' => (__('legacy/log.text_normal')), 'mod' => (__('legacy/log.text_mod'))] as $value => $text)
                        <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                    @endforeach
                    </select>
                @endif
                <input type="hidden" name="action" value="dailylog">
                &nbsp;&nbsp;<input type=submit value="{{ __('legacy/log.submit_search')}}"></form>
        </td></tr>
    </table><br />
    @if (empty($logRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.text_log_empty')))
    @else
        <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center><img class="time" src="pic/trans.gif" alt="time" title="{{ __('legacy/log.title_time_added')}}" /></td><td class=colhead align=left>{{ __('legacy/log.col_event')}}
        @if ($canConfidentialLog)
            <td class=colhead align=left>{{ __('legacy/log.col_user')}}</td>
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
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.time_zone_note')))

@elseif ($mode === 'chronicle')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ __('legacy/log.text_search_chronicle')}}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                <input type="hidden" name="action" value="chronicle">
                &nbsp;&nbsp;<input type=submit value="{{ __('legacy/log.submit_search')}}"></form>
        </td></tr>
    </table><br />
    @if ($canManage)
        <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
            <tr><td class=colhead align=left>{{ ! empty($editItem) ? (__('legacy/log.text_edit_chronicle')) : (__('legacy/log.text_add_chronicle')) }}</td></tr>
            <tr><td class=toolbox align=left>
                <form method="post" action="">
                    <textarea name="txt" style="width:500px" rows="3">{{ ! empty($editItem) ? ($editItem['txt'] ?? '') : (__('legacy/log.text_add_chronicle')) }}</textarea>
                    <input type="hidden" name="action" value="chronicle">
                    <input type="hidden" name="do" value="{{ ! empty($editItem) ? 'update' : 'add' }}">
                    @if (! empty($editItem))
                        <input type="hidden" name="id" value="{{ (int) ($editItem['id'] ?? 0) }}">
                    @endif
                    <input type=submit value="{{ __('legacy/log.submit_add')}}"></form>
            </td></tr>
        </table><br />
    @endif
    @if (empty($chronicleRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.text_chronicle_empty')))
    @else
        <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
        <tr><td class=colhead align=center>{{ __('legacy/log.col_date')}}</td><td class=colhead align=left>{{ __('legacy/log.col_event')}}</td>@if ($canManage)<td class=colhead align=center>{{ __('legacy/log.col_modify')}}</td>@endif</tr>
        @foreach ($chronicleRows as $arr)
            <tr><td class=rowfollow align=center><nobr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['dateHtml'] ?? ''))</nobr></td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['bodyHtml'] ?? ''))</td>@if ($canManage)<td align=center nowrap><b><a href="?action=chronicle&do=edit&id={{ (int) ($arr['id'] ?? 0) }}">{{ __('legacy/log.text_edit')}}</a>&nbsp;|&nbsp;<form method="post" action="?action=chronicle&do=del" class="nx-inline"><input type="hidden" name="id" value="{{ (int) ($arr['id'] ?? 0) }}"><button type="submit" class="nx-btn-link" style="color:red;font-weight:bold">{{ __('legacy/log.text_delete')}}</button></form></b></td>@endif</tr>
        @endforeach
        </table>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.time_zone_note')))

@elseif ($mode === 'news')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=left>{{ __('legacy/log.text_search_news')}}</td></tr>
        <tr><td class=toolbox align=left>
            <form method="get" action="">
                <input type="text" name="query" style="width:500px" value="{{ $q }}">
                {{ __('legacy/log.text_in')}}<select name="search">
                @foreach (['title' => (__('legacy/log.text_title')), 'body' => (__('legacy/log.text_body')), 'both' => (__('legacy/log.text_both'))] as $value => $text)
                    <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                @endforeach
                </select>
                <input type="hidden" name="action" value="news">
                &nbsp;&nbsp;<input type=submit value="{{ __('legacy/log.submit_search')}}"></form>
        </td></tr>
    </table><br />
    @if (empty($newsRows))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.text_news_empty')))
    @else
        @foreach ($newsRows as $arr)
            <table data-nx="data" width=940 border=1 cellspacing=0 cellpadding=5>
            <tr><td class=rowhead width='10%'>{{ __('legacy/log.col_title')}}</td><td class=rowfollow align=left>{{ $arr['title'] ?? '' }}</td></tr><tr><td class=rowhead width='10%'>{{ __('legacy/log.col_date')}}</td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['dateHtml'] ?? ''))</td></tr><tr><td class=rowhead width='10%'>{{ __('legacy/log.col_body')}}</td><td class=rowfollow align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['bodyHtml'] ?? ''))</td></tr>
            </table><br />
        @endforeach
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.time_zone_note')))

@elseif ($mode === 'poll')
    <table data-nx="data" border=1 cellspacing=0 width=940 cellpadding=5>
        <tr><td class=colhead align=center>{{ __('legacy/log.text_previous_polls')}}</td></tr>
    @foreach ($pollData as $item)
        <tr><td align=center>
        <p class=sub>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['added'] ?? ''))
        @if ($canPollManage)
            - [<a href="makepoll.php?action=edit&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ __('legacy/log.text_edit')}}</b></a>]
            - [<a href="?action=poll&do=delete&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ __('legacy/log.text_delete')}}</b></a>]
        @endif
        <a name="{{ (int) ($item['poll']['id'] ?? 0) }}"></a></p>
        <div class="nx-main nx-box">
        <p align=center><b>{{ $item['poll']['question'] ?? '' }}</b></p>
        <div class="nx-main">
        @foreach ($item['options'] ?? [] as $opt)
            <div class="nx-row"><div class="nx-embedded">{{ $opt['text'] ?? '' }}&nbsp;&nbsp;</div><div class="nx-embedded nx-nowrap nx-grow"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="unsltbar" src="pic/trans.gif" style="width: {{ (int) ($opt['percent'] ?? 0) * 3 }}px" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ (int) ($opt['percent'] ?? 0) }}%</div></div>
        @endforeach
        </div>
        <p align=center>{{ __('legacy/log.text_votes')}}{{ $item['totalVotes'] ?? '0' }}</p>
        </div><br /><br />
        </td></tr>
    @endforeach
    </table>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/log.time_zone_note')))
@endif
@endsection
