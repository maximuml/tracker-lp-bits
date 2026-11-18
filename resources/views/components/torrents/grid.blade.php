{{-- Poster-grid listing — .lt-card shape shared with the homepage latest-torrents block. --}}
<div class="lt-grid nx-torrents nxm-tgrid" data-nx="data" role="list" aria-label="{{ __('torrents.head_torrents') }}">
@foreach ($listVm->rows as $row)
<div class="lt-card @if ($row->stickyCount > 0) lt-card--sticky @endif @if ($row->rowClass !== null){{ $row->rowClass }}@endif" role="listitem" @if ($row->stickyCount > 0 && $row->stickyTitle !== '') title="{{ $row->stickyTitle }}" @endif>
    <div class="lt-coverwrap">
        <a class="lt-cover{{ $row->coverSrc === null ? ' lt-cover-empty' : '' }}" href="{{ $row->nameUrl }}" title="{{ $row->nameTitle }}">
            @if ($row->coverSrc !== null)
                <img src="/pic/misc/cover.svg" data-src="{{ $row->coverSrc }}" class="nexus-lazy-load" alt="{{ $row->displayName }}" />
            @else
                <span class="lt-cover-fallback"><x-torrent.category-icon :icon="$row->categoryIcon" :second="$row->secondIcon" /></span>
            @endif
            @if ($row->categoryIcon !== null)
                <span class="lt-type">{{ $row->categoryIcon->name }}</span>
            @endif
            <span class="lt-badges">
                @if ($row->isNew)<span class="nxm-pill nxm-pill--new">{{ __('functions.text_new_uppercase') }}</span>@endif
                @if ($row->isBanned)<span class="nxm-pill nxm-pill--banned">{{ __('functions.text_banned') }}</span>@endif
                @if ($row->waitText !== null)<span class="nxm-pill nxm-pill--wait @if ($row->waitClass !== null){{ $row->waitClass }}@endif">{{ $row->waitText }}</span>@endif
                <x-torrent.badges :set="$row->badges" />
            </span>
            <span class="lt-stats">
                <span class="lt-seed" title="{{ $listVm->columnLabel('seeders') }}">&#x25B2; {{ number_format($row->seeders) }}</span>
                <span class="lt-leech" title="{{ $listVm->columnLabel('leechers') }}">&#x25BC; {{ number_format($row->leechers) }}</span>
                <span title="{{ $listVm->columnLabel('snatched') }}">&#x25CF; {{ number_format($row->snatched) }}</span>
            </span>
        </a>
        <div class="lt-acts">
            @if ($row->showDownload)<a class="lt-act" href="{{ $row->downloadUrl }}" aria-label="{{ __('functions.title_download_torrent') }}" title="{{ __('functions.title_download_torrent') }}"><i class="download" role="img"></i></a>@endif
            @if ($row->showBookmark)<span class="lt-act"><x-torrent.bookmark :torrent-id="$row->id" :bookmarked="$row->bookmarked" /></span>@endif
        </div>
    </div>
    <div class="lt-title">
        <a href="{{ $row->nameUrl }}" title="{{ $row->nameTitle }}"><b>{{ $row->displayName }}</b></a>
    </div>
    <div class="lt-meta">
        <span>{{ $row->size['value'] }} {{ $row->size['unit'] }}</span>
        <span>
            @if ($row->uploaderAnonymous)
                <i>{{ __('functions.text_anonymous') }}</i>@if ($row->uploaderShowOwner) @if ($row->uploaderName)({{ $row->uploaderName }})@endif @endif
            @elseif ($row->uploaderName)
                {{ $row->uploaderName }}
            @else
                <i>{{ __('functions.text_orphaned') }}</i>
            @endif
        </span>
        @if ($listVm->showComments && $row->comments > 0)
            <a href="{{ $row->commentsUrl }}" title="{{ $listVm->columnLabel('comments') }}"@if ($row->lastCommentTooltipId) data-domtt-src="{{ $row->lastCommentTooltipId }}"@endif>{{ $row->comments }} {{ $listVm->columnLabel('comments') }}</a>
        @endif
    </div>
    @if ($row->progress !== null)<div class="lt-progress"><x-torrent.progress :progress="$row->progress" /></div>@endif
</div>
@endforeach
</div>
@if ($listVm->showPromotionNote)
<p class="nxm-note nx-center"><b>{{ __('functions.text_promoted_torrents_note') }}&nbsp;&nbsp;</b> <a href="{{ request()->getPathInfo() }}?spstate=2" target="_self" title="{{ __('functions.title_spstate_2') }}"><span class="free"><b>{{ __('functions.text_free') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=3" target="_self" title="{{ __('functions.title_spstate_3') }}"><span class="twoup"><b>{{ __('functions.text_twoup') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=4" target="_self" title="{{ __('functions.title_spstate_4') }}"><span class="twoupfree"><b>{{ __('functions.text_twoupfree') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=5" target="_self" title="{{ __('functions.title_spstate_5') }}"><span class="halfdown"><b>{{ __('functions.text_halfdown') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=6" target="_self" title="{{ __('functions.title_spstate_6') }}"><span class="twouphalfdown"><b>{{ __('functions.text_twouphalfdown') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=7" target="_self" title="{{ __('functions.title_spstate_7') }}"><span class="thirtypercent"><b>{{ __('functions.text_thirtydown') }}</b></span></a><b>&nbsp;{{ __('functions.text_torrents_word') }}</b><br />{{ __('functions.text_leeching_tip') }}</p>
@endif
@if ($listVm->lastCommentTooltips !== [])
<div class="nx-hidden">
    @foreach ($listVm->lastCommentTooltips as $tip)<div id="{{ $tip['id'] }}">{{ $tip['content'] }}</div>@endforeach
</div>
@endif
