{{-- Modern torrents page body (Variant A, ADR 0014): search panel + torrent table + pager. --}}
<div class="nxm-torrents">

@include('components.torrents.search_form')

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
    <div class="nxm-results" role="status">
        <span>{{ __('torrents.results_found', ['count' => number_format($count)]) }}</span>
        <details class="nxm-sortmenu">
            <summary class="nxm-sortmenu__current">{{ $listVm->sortLabel }}</summary>
            <div class="nxm-sortmenu__list">
                @foreach ($listVm->columns as $col)
                    @if ($col['sortUrl'] !== null)
                    <a class="nxm-sortmenu__item @if (($col['ariaSort'] ?? null) !== null) nxm-th--sorted @endif" href="{{ $col['sortUrl'] }}">{{ $col['abbrTitle'] ?? $col['shortLabel'] ?? $col['label'] }}@if (($col['ariaSort'] ?? null) === 'descending') ▼@elseif (($col['ariaSort'] ?? null) === 'ascending') ▲@endif</a>
                    @endif
                @endforeach
            </div>
        </details>
        @include('components.torrents.view-toggle')
    </div>

    <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />
    @include('components.torrents.list')
    <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
@else
    <div class="nxm-empty" role="status">
        <p class="nxm-empty__title">{{ $emptyTitle }}</p>
        <p class="nxm-empty__body">{{ $emptyBody }}</p>
        @if ($panelVm->appliedFilters !== [])
            <p class="nxm-empty__actions"><a class="nx-btn nx-btn--ghost" href="{{ $panelVm->resetUrl }}">{{ __('torrents.reset_all') }}</a></p>
        @endif
    </div>
@endif

</div>
