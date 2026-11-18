{{-- Modern torrents listing (Variant A, ADR 0014). Row-cards replace TorrentTable::render — data arrives prepared in $listVm. --}}
<div class="nx-torrents nxm-tlist" data-nx="data" role="list" aria-label="{{ __('torrents.head_torrents') }}">
@foreach ($listVm->rows as $row)
<article class="nxm-trow @if ($row->stickyCount > 0) nxm-trow--sticky @endif @if ($row->rowClass !== null){{ $row->rowClass }}@endif" role="listitem" @if ($row->stickyCount > 0 && $row->stickyTitle !== '') title="{{ $row->stickyTitle }}" @endif>
    <a class="nxm-trow__poster" href="{{ $row->nameUrl }}" aria-hidden="true" tabindex="-1">
        @if ($row->coverSrc !== null)
        <img src="/pic/misc/cover.svg" data-src="{{ $row->coverSrc }}" class="nexus-lazy-load nxm-trow__cover" alt="" />
        @else
        <span class="nxm-trow__caticon"><x-torrent.category-icon :icon="$row->categoryIcon" :second="$row->secondIcon" /></span>
        @endif
    </a>
    <div class="nxm-trow__main">
        <div class="nxm-trow__title">
            <a class="nxm-trow__name" title="{{ $row->nameTitle }}" href="{{ $row->nameUrl }}"><b>{{ $row->displayName }}</b></a>
            @if ($row->isNew)<span class="nxm-pill nxm-pill--new">{{ __('functions.text_new_uppercase') }}</span>@endif
            @if ($row->isBanned)<span class="nxm-pill nxm-pill--banned">{{ __('functions.text_banned') }}</span>@endif
            @if ($row->waitText !== null)<a href="/web/faq#id46"><span class="nxm-pill nxm-pill--wait @if ($row->waitClass !== null){{ $row->waitClass }}@endif">{{ $row->waitText }}</span></a>@endif
            <x-torrent.badges :set="$row->badges" />
        </div>
        <div class="nxm-trow__meta">
            @if ($row->categoryIcon !== null)<span>{{ $row->categoryIcon->name }}</span><span class="nxm-trow__dim">·</span>@endif
            <time datetime="{{ str_replace(' ', 'T', (string) $row->added) }}">{{ $row->addedDate }} {{ $row->addedTime }}</time>
            <span class="nxm-trow__dim">·</span>
            @if ($row->uploaderAnonymous)
                <i>{{ __('functions.text_anonymous') }}</i>@if ($row->uploaderShowOwner) @if ($row->uploaderName)({{ $row->uploaderName }})@else(<i>{{ __('functions.text_orphaned') }}</i>)@endif @endif
            @elseif ($row->uploaderName)
                {{ $row->uploaderName }}
            @else
                <i>{{ __('functions.text_orphaned') }}</i>
            @endif
            <span class="nxm-trow__dim">·</span>
            <span>{{ $row->size['value'] }} {{ $row->size['unit'] }}</span>
            @if ($listVm->showComments)
            <span class="nxm-trow__dim">·</span>
            @if ($row->comments === 0)
                <a class="nxm-trow__comments" href="/comment/add?pid={{ $row->id }}&amp;type=torrent" title="{{ __('functions.title_add_comments') }}">0 {{ $listVm->columnLabel('comments') }}</a>
            @else
                <a class="nxm-trow__comments" href="{{ $row->commentsUrl }}"@if ($row->lastCommentTooltipId) data-domtt-src="{{ $row->lastCommentTooltipId }}"@endif>@if ($row->commentIsNew)<span class="new">@endif{{ $row->comments }} {{ $listVm->columnLabel('comments') }}@if ($row->commentIsNew)</span>@endif</a>
            @endif
            @endif
        </div>
        @if ($row->tags !== [])
        <div class="nxm-trow__tags"><x-torrent.tags :tags="$row->tags" /></div>
        @endif
    </div>
    <div class="nxm-trow__stats">
        <a @if ($row->seedersUrl) href="{{ $row->seedersUrl }}" @endif class="nxm-trow__stat nxm-trow__stat--s" title="{{ $listVm->columnLabel('seeders') }}"><b>@if ($row->seedersClass)<span class="{{ $row->seedersClass }}">{{ number_format($row->seeders) }}</span>@else{{ number_format($row->seeders) }}@endif</b><span>S</span></a>
        <a @if ($row->leechersUrl) href="{{ $row->leechersUrl }}" @endif class="nxm-trow__stat nxm-trow__stat--l" title="{{ $listVm->columnLabel('leechers') }}"><b>{{ number_format($row->leechers) }}</b><span>L</span></a>
        <a @if ($row->snatchedUrl) href="{{ $row->snatchedUrl }}" @endif class="nxm-trow__stat nxm-trow__stat--sn" title="{{ $listVm->columnLabel('snatched') }}"><b>{{ number_format($row->snatched) }}</b><span>Sn</span></a>
        @if ($row->progress !== null)<x-torrent.progress :progress="$row->progress" />@endif
    </div>
    <div class="nxm-trow__acts">
        @if ($row->showDownload)<a class="nxm-trow__act" href="{{ $row->downloadUrl }}"><i class="download" role="img" aria-label="{{ __('functions.title_download_torrent') }}" title="{{ __('functions.title_download_torrent') }}"></i></a>@endif
        @if ($row->showBookmark)<span class="nxm-trow__act"><x-torrent.bookmark :torrent-id="$row->id" :bookmarked="$row->bookmarked" /></span>@endif
    </div>
</article>
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
