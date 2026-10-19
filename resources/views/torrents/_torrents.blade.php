{{-- Modern torrents page body (Variant A, ADR 0014): search panel + torrent table + pager. --}}
<div class="nxm-torrents">

@include('torrents._search_form')

@if ($inclbookmarked == 1)
    <h1 class="nxm-pagehead">{{ $bookmarkedUsername }}{{ __('torrents.text_s_bookmarked_torrent') }}</h1>
@elseif ($inclbookmarked == 2)
    <h1 class="nxm-pagehead">{{ $bookmarkedUsername }}{{ __('torrents.text_s_not_bookmarked_torrent') }}</h1>
@endif

@if ($panelVm->appliedFilters !== [])
    <section class="nxm-applied" aria-labelledby="nxm-applied-title">
        <h2 id="nxm-applied-title" class="nxm-applied__title">{{ __('torrents.applied_filters') }}</h2>
        <ul class="nxm-applied__list">
            @foreach ($panelVm->appliedFilters as $f)
                <li>
                    <a class="nx-chip nxm-applied__chip" href="{{ $f['removeUrl'] }}" aria-label="{{ __('torrents.remove_filter', ['filter' => $f['label']]) }}">{{ $f['label'] }} <span aria-hidden="true">×</span></a>
                </li>
            @endforeach
        </ul>
        <a class="nx-btn nx-btn--ghost" href="{{ $panelVm->resetUrl }}">{{ __('torrents.reset_all') }}</a>
    </section>
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
