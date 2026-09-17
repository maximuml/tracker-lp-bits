{{-- Modern torrents page body (Variant A, ADR 0014): search panel + torrent table + pager. --}}
<div class="nxm-torrents">

@include('torrents._search_form')

@if ($inclbookmarked == 1)
    <h1 class="nxm-pagehead">{{ $bookmarkedUsername }}{{ __('legacy/torrents.text_s_bookmarked_torrent') }}</h1>
@elseif ($inclbookmarked == 2)
    <h1 class="nxm-pagehead">{{ $bookmarkedUsername }}{{ __('legacy/torrents.text_s_not_bookmarked_torrent') }}</h1>
@endif

@if ($count && isset($rows))

    {{ $pagertop }}
    @include('torrents._table')
    {{ $pagerbottom }}
@else
    <div class="nxm-empty" role="status">
        <p class="nxm-empty__title">{{ $emptyTitle }}</p>
        <p class="nxm-empty__body">{{ $emptyBody }}</p>
    </div>
@endif

</div>
