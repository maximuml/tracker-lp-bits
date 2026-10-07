{{-- Modern torrents listing table (Variant A, ADR 0014). Replaces TorrentTable::render — data arrives prepared in $listVm. --}}
<table class="nx-torrents nxm-table" data-nx="data"><caption class="nx-sr-only">{{ __('torrents.head_torrents') }}</caption>
<thead>
<tr>
    @foreach ($listVm->columns as $col)
    <th class="nx-colhead @if (($col['thClass'] ?? '') !== ''){{ $col['thClass'] }}@endif" scope="col">
        @if ($col['sortUrl'])<a href="{{ $col['sortUrl'] }}">@endif
            @if ($col['iconClass'])<img class="{{ $col['iconClass'] }}" src="pic/trans.gif" alt="{{ (($col['shortLabel'] ?? '') !== '' || $col['label'] !== '') ? '' : $col['iconTitle'] }}" title="{{ $col['iconTitle'] }}" />@endif
            @if (($col['shortLabel'] ?? '') !== '' || $col['label'] !== '')<span class="nxm-th__label">{{ ($col['shortLabel'] ?? '') !== '' ? $col['shortLabel'] : $col['label'] }}</span>@endif
        @if ($col['sortUrl'])</a>@endif
    </th>
    @endforeach
</tr>
</thead>
<tbody>
@foreach ($listVm->rows as $row)
<tr @if ($row->rowClass !== null) class="{{ $row->rowClass }}" @endif>
    <td class="nx-rowfollow nowrap nxm-td-icon"><x-torrent.category-icon :icon="$row->categoryIcon" :second="$row->secondIcon" /></td>
    <td class="nx-rowfollow nxm-td-name nx-w-99p">
        <div class="torrentname nxm-nameblock">
            @if ($row->coverSrc !== null)
            <div class="nx-embedded nxm-cover"><img src="pic/misc/cover.svg" data-src="{{ $row->coverSrc }}" class="nexus-lazy-load nxm-cover__img" alt="" /></div>
            @endif
            <div class="nx-embedded nxm-namecell">@for ($i = 0; $i < $row->stickyCount; $i++)<img class="sticky" src="pic/trans.gif" alt="Sticky" title="{{ $row->stickyTitle }}" />&nbsp;@endfor<a title="{{ $row->nameTitle }}" href="{{ $row->nameUrl }}"><b>{{ $row->displayName }}</b></a>@if ($row->isNew) <b>(<span class="new">{{ __('functions.text_new_uppercase') }}</span>)</b>@endif @if ($row->isBanned) <b>(<span class="striking">{{ __('functions.text_banned') }}</span>)</b>@endif<x-torrent.badges :set="$row->badges" />@if ($row->tags !== [])<br /><x-torrent.tags :tags="$row->tags" />@endif<x-torrent.progress :progress="$row->progress" /></div>
            <div class="nx-embedded nxm-rowactions">
                @if ($row->showDownload)<a href="{{ $row->downloadUrl }}"><img class="download" src="pic/trans.gif" alt="download" title="{{ __('functions.title_download_torrent') }}" /></a>@endif
                @if ($row->showDownload && $row->showBookmark)<br />@endif
                @if ($row->showBookmark)<livewire:bookmark-icon :torrent-id="$row->id" />@endif
            </div>
        </div>
    </td>
    @if ($row->waitText !== null)
    <td class="nx-rowfollow nowrap nxm-td-wait">@if ($row->waitClass !== null)<a href="/web/faq#id46"><span class="{{ $row->waitClass }}">{{ $row->waitText }}</span></a>@else{{ $row->waitText }}@endif</td>
    @endif
    @if ($listVm->showComments)
    <td class="nx-rowfollow nxm-td-comments" data-label="{{ 'Com' }}">
        @if ($row->comments === 0)
            <a href="/comment/add?amp;pid={{ $row->id }}&amp;type=torrent" title="{{ __('functions.title_add_comments') }}">0</a>
        @else
            <b><a href="{{ $row->commentsUrl }}"@if ($row->lastCommentTooltipId) data-domtt-src="{{ $row->lastCommentTooltipId }}"@endif>@if ($row->commentIsNew)<span class="new">@endif{{ $row->comments }}@if ($row->commentIsNew)</span>@endif</a></b>
        @endif
    </td>
    @endif
    <td class="nx-rowfollow nowrap nxm-td-added"><time datetime="{{ str_replace(' ', 'T', (string) $row->added) }}">{{ $row->addedDate }} <br/>{{ $row->addedTime }}</time></td>
    <td class="nx-rowfollow nowrap nxm-td-size">{{ $row->size['value'] }} <br/>{{ $row->size['unit'] }}</td>
    <td class="nx-rowfollow nxm-td-seeders nx-center" data-label="{{ 'S' }}">
        @if ($row->seedersUrl)
            <b><a href="{{ $row->seedersUrl }}">@if ($row->seedersClass)<span class="{{ $row->seedersClass }}">{{ number_format($row->seeders) }}</span>@else{{ number_format($row->seeders) }}@endif</a></b>
        @else
            <span class="{{ $row->seedersZeroClass }}">{{ number_format($row->seeders) }}</span>
        @endif
    </td>
    <td class="nx-rowfollow nxm-td-leechers nx-center" data-label="{{ 'L' }}">@if ($row->leechersUrl)<b><a href="{{ $row->leechersUrl }}">{{ number_format($row->leechers) }}</a></b>@else{{ $row->leechers }}@endif</td>
    <td class="nx-rowfollow nxm-td-snatched nx-center" data-label="{{ 'Sn' }}">@if ($row->snatchedUrl)<a href="{{ $row->snatchedUrl }}"><b>{{ number_format($row->snatched) }}</b></a>@else{{ number_format($row->snatched) }}@endif</td>
    <td class="nx-rowfollow nxm-td-uploader nx-center">
        @if ($row->uploaderAnonymous)
            <i>{{ __('functions.text_anonymous') }}</i>@if ($row->uploaderShowOwner)<br />@if ($row->uploaderName)({{ $row->uploaderName }})@else(<i>{{ __('functions.text_orphaned') }}</i>)@endif @endif
        @elseif ($row->uploaderName)
            {{ $row->uploaderName }}
        @else
            <i>{{ __('functions.text_orphaned') }}</i>
        @endif
    </td>
</tr>
@endforeach
</tbody>
</table>
@if ($listVm->showPromotionNote)
<p class="nxm-note nx-center"><b>{{ __('functions.text_promoted_torrents_note') }}&nbsp;&nbsp;</b> <a href="{{ request()->getPathInfo() }}?spstate=2" target="_self" title="{{ __('functions.title_spstate_2') }}"><span class="free"><b>{{ __('functions.text_free') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=3" target="_self" title="{{ __('functions.title_spstate_3') }}"><span class="twoup"><b>{{ __('functions.text_twoup') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=4" target="_self" title="{{ __('functions.title_spstate_4') }}"><span class="twoupfree"><b>{{ __('functions.text_twoupfree') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=5" target="_self" title="{{ __('functions.title_spstate_5') }}"><span class="halfdown"><b>{{ __('functions.text_halfdown') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=6" target="_self" title="{{ __('functions.title_spstate_6') }}"><span class="twouphalfdown"><b>{{ __('functions.text_twouphalfdown') }}</b></span></a> | <a href="{{ request()->getPathInfo() }}?spstate=7" target="_self" title="{{ __('functions.title_spstate_7') }}"><span class="thirtypercent"><b>{{ __('functions.text_thirtydown') }}</b></span></a><b>&nbsp;{{ __('functions.text_torrents_word') }}</b><br />{{ __('functions.text_leeching_tip') }}</p>
@endif
@if ($listVm->lastCommentTooltips !== [])
<div class="nx-hidden">
    @foreach ($listVm->lastCommentTooltips as $tip)<div id="{{ $tip['id'] }}">{{ $tip['content'] }}</div>@endforeach
</div>
@endif
