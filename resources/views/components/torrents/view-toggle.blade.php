{{-- List/Posters segmented switch — ?view= persists users.torrents_view. --}}
<nav class="nxm-viewseg" aria-label="{{ __('torrents.head_torrents') }}">
    <a class="nxm-viewseg__btn @if ($listVm->viewMode === \App\Enums\TorrentListView::List) is-active @endif" href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" @if ($listVm->viewMode === \App\Enums\TorrentListView::List) aria-current="true" @endif>{{ __('torrents.view_list') }}</a>
    <a class="nxm-viewseg__btn @if ($listVm->viewMode === \App\Enums\TorrentListView::Grid) is-active @endif" href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" @if ($listVm->viewMode === \App\Enums\TorrentListView::Grid) aria-current="true" @endif>{{ __('torrents.view_grid') }}</a>
</nav>
