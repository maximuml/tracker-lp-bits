<table width="97%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">

@include('torrents._search_form')

@if ($inclbookmarked == 1)
    <h1 align="center">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserDisplay::username($CURUSER['id']))){{ $lang_torrents['text_s_bookmarked_torrent'] }}</h1>
@elseif ($inclbookmarked == 2)
    <h1 align="center">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserDisplay::username($CURUSER['id']))){{ $lang_torrents['text_s_not_bookmarked_torrent'] }}</h1>
@endif

@if ($count && isset($rows))

    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
    @if ($sectiontype == $browsecatmode)
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\TorrentTable::render($rows, 'torrents', $sectiontype)))
    @else
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\TorrentTable::render($rows, 'bookmarks', $sectiontype)))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@else
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($emptyMessageHtml ?? ''))
@endif

</td></tr></table>
