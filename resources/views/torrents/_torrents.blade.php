<table width="97%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">

@include('torrents._search_form')

@if ($inclbookmarked == 1)
    <h1 align="center">{!! \App\Support\UserDisplay::username($CURUSER['id']) !!}{{ $lang_torrents['text_s_bookmarked_torrent'] }}</h1>
@elseif ($inclbookmarked == 2)
    <h1 align="center">{!! \App\Support\UserDisplay::username($CURUSER['id']) !!}{{ $lang_torrents['text_s_not_bookmarked_torrent'] }}</h1>
@endif

@if ($count)
    @php
        if ($shouldUseMeili) {
            $rows = $resultFromSearchRep['list'];
        } else {
            $fieldsArr = \App\Models\Torrent::getFieldsForList(true);
            $rows = \App\Repositories\TorrentListingRepository::getList(array_merge($listingOptions, [
                'fields' => $fieldsArr,
                'search_box_id' => $sectiontype,
                'order_by' => $orderby,
                'offset' => $offset,
                'limit' => $size,
            ]));
        }
        $rows = apply_filter('torrent_list', $rows, $page ?? 0, $sectiontype, $searchstr_raw ?? '');
    @endphp

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
            \App\Support\SupportContext::addUserUpdate('last_browse', TIMENOW);
        } else {
            \App\Support\SupportContext::addUserUpdate('last_music', TIMENOW);
        }
    @endphp
@endif

</td></tr></table>
