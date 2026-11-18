{{-- Listing wrapper: picks the row-card or poster-grid renderer by the persisted view mode. --}}
@if ($listVm->viewMode === \App\Enums\TorrentListView::Grid)
    @include('components.torrents.grid')
@else
    @include('components.torrents.table')
@endif
