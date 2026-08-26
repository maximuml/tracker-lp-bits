<table width="97%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">

@include('torrents._search_form')

@if ($inclbookmarked == 1)
    <h1 align="center">{!! \App\Support\UserDisplay::username($CURUSER['id']) !!}{{ $lang_torrents['text_s_bookmarked_torrent'] }}</h1>
@elseif ($inclbookmarked == 2)
    <h1 align="center">{!! \App\Support\UserDisplay::username($CURUSER['id']) !!}{{ $lang_torrents['text_s_not_bookmarked_torrent'] }}</h1>
@endif

@if ($count && isset($rows))

    {!! $pagertop !!}
    @if ($sectiontype == $browsecatmode)
        {!! \App\Support\TorrentTable::render($rows, 'torrents', $sectiontype) !!}
    @else
        {!! \App\Support\TorrentTable::render($rows, 'bookmarks', $sectiontype) !!}
    @endif
    {!! $pagerbottom !!}
@else
    @if (isset($searchstr))
        <br />
        @php \App\Support\Html::stdMessage($lang_torrents['std_search_results_for'] . $searchstr_ori . '"', $lang_torrents['std_try_again']); @endphp
    @else
        @php \App\Support\Html::stdMessage($lang_torrents['std_nothing_found'], $lang_torrents['std_no_active_torrents']); @endphp
    @endif
@endif

@if ($CURUSER)
    @php
        if ($sectiontype == $browsecatmode) {
            \app(\App\Support\UserUpdateBatch::class)->add('last_browse', TIMENOW);
        } else {
            \app(\App\Support\UserUpdateBatch::class)->add('last_music', TIMENOW);
        }
    @endphp
@endif

</td></tr></table>
