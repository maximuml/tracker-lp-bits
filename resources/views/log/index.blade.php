@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('legacy/log.head_site_log')))

@section('content')
<div id="lognav"><ul id="logmenu" class="menu">
@foreach (['dailylog' => (__('legacy/log.text_daily_log')), 'chronicle' => (__('legacy/log.text_chronicle')), 'news' => (__('legacy/log.text_news')), 'poll' => (__('legacy/log.text_poll'))] as $a => $label)
    <li{{ $mode === $a ? ' class=selected' : '' }}><a href="?action={{ $a }}">{{ $label }}</a></li>
@endforeach
</ul></div>

@if ($mode === 'dailylog')
    <x-data-table :caption="__('legacy/log.text_search_log')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.text_search_log')}}</th></tr></thead></x-slot:head>
        <tr><td class="toolbox">
            <form method="get" action="">
                <input type="text" name="query" value="{{ $q }}">
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
    </x-data-table><br />
    @if (empty($logRows))
        <b>{{ __('legacy/log.text_log_empty') }}</b><br />
    @else
        <x-data-table :caption="__('legacy/log.text_daily_log')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col"><img class="time" src="pic/trans.gif" alt="time" title="{{ __('legacy/log.title_time_added')}}" /></th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.col_event')}}</th>
        @if ($canConfidentialLog)
            <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.col_user')}}</th>
        @endif
        </tr></thead></x-slot:head>
        @foreach ($logRows as $arr)
            <tr><td class="align-top px-2.5 py-1.5 whitespace-nowrap text-center">{{ $arr['dateHtml'] ?? '' }}</td><td class="align-top px-2.5 py-1.5"><span class="{{ $arr['colorClass'] ?? '' }}">{{ $arr['txt'] ?? '' }}</span></td>
            @if ($canConfidentialLog)
                <td class="align-top px-2.5 py-1.5">{{ $arr['usernameHtml'] ?? '' }}</td>
            @endif
            </tr>
        @endforeach
        </x-data-table>
        {{ $pagerbottom ?? '' }}
    @endif
    <p>{{ __('legacy/log.time_zone_note') }}</p>

@elseif ($mode === 'chronicle')
    <x-data-table :caption="__('legacy/log.text_search_chronicle')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.text_search_chronicle')}}</th></tr></thead></x-slot:head>
        <tr><td class="toolbox">
            <form method="get" action="">
                <input type="text" name="query" value="{{ $q }}">
                <input type="hidden" name="action" value="chronicle">
                &nbsp;&nbsp;<input type=submit value="{{ __('legacy/log.submit_search')}}"></form>
        </td></tr>
    </x-data-table><br />
    @if ($canManage)
        <x-data-table :caption="! empty($editItem) ? __('legacy/log.text_edit_chronicle') : __('legacy/log.text_add_chronicle')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ ! empty($editItem) ? (__('legacy/log.text_edit_chronicle')) : (__('legacy/log.text_add_chronicle')) }}</th></tr></thead></x-slot:head>
            <tr><td class="toolbox">
                <form method="post" action="">
                    <textarea name="txt" rows="3">{{ ! empty($editItem) ? ($editItem['txt'] ?? '') : (__('legacy/log.text_add_chronicle')) }}</textarea>
                    <input type="hidden" name="action" value="chronicle">
                    <input type="hidden" name="do" value="{{ ! empty($editItem) ? 'update' : 'add' }}">
                    @if (! empty($editItem))
                        <input type="hidden" name="id" value="{{ (int) ($editItem['id'] ?? 0) }}">
                    @endif
                    <input type=submit value="{{ __('legacy/log.submit_add')}}"></form>
            </td></tr>
        </x-data-table><br />
    @endif
    @if (empty($chronicleRows))
        <b>{{ __('legacy/log.text_chronicle_empty') }}</b><br />
    @else
        <x-data-table :caption="__('legacy/log.text_chronicle')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/log.col_date')}}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.col_event')}}</th>@if ($canManage)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/log.col_modify')}}</th>@endif</tr></thead></x-slot:head>
        @foreach ($chronicleRows as $arr)
            <tr><td class="align-top px-2.5 py-1.5 text-center"><nobr>{{ $arr['dateHtml'] ?? '' }}</nobr></td><td class="align-top px-2.5 py-1.5">{{ $arr['bodyHtml'] ?? '' }}</td>@if ($canManage)<td class="text-center whitespace-nowrap"><b><a href="?action=chronicle&do=edit&id={{ (int) ($arr['id'] ?? 0) }}">{{ __('legacy/log.text_edit')}}</a>&nbsp;|&nbsp;<form method="post" action="/web/log/chronicle/delete" class="inline"><input type="hidden" name="id" value="{{ (int) ($arr['id'] ?? 0) }}"><button type="submit" class="nx-btn-link">{{ __('legacy/log.text_delete')}}</button></form></b></td>@endif</tr>
        @endforeach
        </x-data-table>
        {{ $pagerbottom ?? '' }}
    @endif
    <p>{{ __('legacy/log.time_zone_note') }}</p>

@elseif ($mode === 'news')
    <x-data-table :caption="__('legacy/log.text_search_news')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/log.text_search_news')}}</th></tr></thead></x-slot:head>
        <tr><td class="toolbox">
            <form method="get" action="">
                <input type="text" name="query" value="{{ $q }}">
                {{ __('legacy/log.text_in')}}<select name="search">
                @foreach (['title' => (__('legacy/log.text_title')), 'body' => (__('legacy/log.text_body')), 'both' => (__('legacy/log.text_both'))] as $value => $text)
                    <option value='{{ $value }}'{{ $value === $search ? ' selected' : '' }}>{{ $text }}</option>
                @endforeach
                </select>
                <input type="hidden" name="action" value="news">
                &nbsp;&nbsp;<input type=submit value="{{ __('legacy/log.submit_search')}}"></form>
        </td></tr>
    </x-data-table><br />
    @if (empty($newsRows))
        <b>{{ __('legacy/log.text_news_empty') }}</b><br />
    @else
        @foreach ($newsRows as $arr)
            <x-data-table :caption="__('legacy/log.text_news')" captionHidden>
            <tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim w-[10%]">{{ __('legacy/log.col_title')}}</td><td class="align-top px-2.5 py-1.5">{{ $arr['title'] ?? '' }}</td></tr><tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim w-[10%]">{{ __('legacy/log.col_date')}}</td><td class="align-top px-2.5 py-1.5">{{ $arr['dateHtml'] ?? '' }}</td></tr><tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim w-[10%]">{{ __('legacy/log.col_body')}}</td><td class="align-top px-2.5 py-1.5">{{ $arr['bodyHtml'] ?? '' }}</td></tr>
            </x-data-table><br />
        @endforeach
        {{ $pagerbottom ?? '' }}
    @endif
    <p>{{ __('legacy/log.time_zone_note') }}</p>

@elseif ($mode === 'poll')
    <x-data-table :caption="__('legacy/log.text_previous_polls')" captionHidden><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/log.text_previous_polls')}}</th></tr></thead></x-slot:head>
    @foreach ($pollData as $item)
        <tr><td class="text-center">
        <p class=sub>{{ $item['added'] ?? '' }}
        @if ($canPollManage)
            - [<a href="/web/makepoll?action=edit&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ __('legacy/log.text_edit')}}</b></a>]
            - [<a href="?action=poll&do=delete&pollid={{ (int) ($item['poll']['id'] ?? 0) }}"><b>{{ __('legacy/log.text_delete')}}</b></a>]
        @endif
        <a name="{{ (int) ($item['poll']['id'] ?? 0) }}"></a></p>
        <div class="nx-main nx-box">
        <p class="text-center"><b>{{ $item['poll']['question'] ?? '' }}</b></p>
        <div class="nx-main">
        @foreach ($item['options'] ?? [] as $opt)
            <div class="flex items-start"><div class="nx-embedded">{{ $opt['text'] ?? '' }}&nbsp;&nbsp;</div><div class="nx-embedded whitespace-nowrap grow"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="unsltbar" src="pic/trans.gif" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ (int) ($opt['percent'] ?? 0) }}%</div></div>
        @endforeach
        </div>
        <p class="text-center">{{ __('legacy/log.text_votes')}}{{ $item['totalVotes'] ?? '0' }}</p>
        </div><br /><br />
        </td></tr>
    @endforeach
    </x-data-table>
    <p>{{ __('legacy/log.time_zone_note') }}</p>
@endif
@endsection
