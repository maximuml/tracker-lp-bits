{{-- Modern torrents listing table (Variant A, ADR 0014). Replaces TorrentTable::render — data arrives prepared in $listVm. --}}
<table class="nx-torrents nxm-table" data-nx="data" cellspacing="0" cellpadding="5" width="100%"><caption class="nx-sr-only">{{ __('legacy/torrents.head_torrents') }}</caption>
<thead>
<tr>
    @foreach ($listVm->columns as $col)
    <th class="colhead" scope="col">
        @if ($col['sortUrl'])
            <a href="{{ $col['sortUrl'] }}">
                @if ($col['iconClass'])<img class="{{ $col['iconClass'] }}" src="pic/trans.gif" alt="{{ $col['iconTitle'] }}" title="{{ $col['iconTitle'] }}" />@else{{ $col['label'] }}@endif
            </a>
        @elseif ($col['iconClass'])
            <img class="{{ $col['iconClass'] }}" src="pic/trans.gif" alt="{{ $col['iconTitle'] }}" title="{{ $col['iconTitle'] }}" />
        @else
            {{ $col['label'] }}
        @endif
    </th>
    @endforeach
</tr>
</thead>
<tbody>
@foreach ($listVm->rows as $row)
<tr @if ($row->rowClass !== null) class="{{ $row->rowClass }}" @endif>
    <td class="rowfollow nowrap nxm-td-icon" valign="middle"><x-torrent.category-icon :icon="$row->categoryIcon" :second="$row->secondIcon" /></td>
    <td class="rowfollow nxm-td-name" width="100%" align="left">
        <div class="torrentname nxm-nameblock">
            @if ($row->coverSrc !== null)
            <div class="nx-embedded nxm-cover"><img src="pic/misc/spinner.svg" data-src="{{ $row->coverSrc }}" class="nexus-lazy-load nxm-cover__img" alt="" /></div>
            @endif
            <div class="nx-embedded nxm-namecell">@for ($i = 0; $i < $row->stickyCount; $i++)<img class="sticky" src="pic/trans.gif" alt="Sticky" title="{{ $row->stickyTitle }}" />&nbsp;@endfor<a title="{{ $row->nameTitle }}" href="{{ $row->nameUrl }}"><b>{{ $row->displayName }}</b></a>@if ($row->isNew) <b>(<span class="new">{{ __('legacy/functions.text_new_uppercase') }}</span>)</b>@endif @if ($row->isBanned) <b>(<span class="striking">{{ __('legacy/functions.text_banned') }}</span>)</b>@endif<x-torrent.badges :set="$row->badges" />@if ($row->tags !== [])<br /><x-torrent.tags :tags="$row->tags" />@endif<x-torrent.progress :progress="$row->progress" /></div>
            <div class="nx-embedded nxm-rowactions">
                @if ($row->showDownload)<a href="{{ $row->downloadUrl }}"><img class="download" src="pic/trans.gif" alt="download" title="{{ __('legacy/functions.title_download_torrent') }}" /></a>@endif
                @if ($row->showDownload && $row->showBookmark)<br />@endif
                @if ($row->showBookmark)<a id="{{ $row->bookmarkElementId }}" href="#" data-bookmark-torrent="{{ $row->id }}" data-bookmark-counter="{{ $row->bookmarkCounter }}"><img class="{{ $row->bookmarked ? 'bookmark' : 'delbookmark' }}" src="pic/trans.gif" alt="{{ $row->bookmarked ? 'Bookmarked' : 'Unbookmarked' }}" title="{{ $row->bookmarked ? __('legacy/functions.title_delbookmark_torrent') : __('legacy/functions.title_bookmark_torrent') }}" /></a>@endif
            </div>
        </div>
    </td>
    @if ($row->waitText !== null)
    <td class="rowfollow nowrap nxm-td-wait">@if ($row->waitClass !== null)<a href="faq.php#id46"><span class="{{ $row->waitClass }}">{{ $row->waitText }}</span></a>@else{{ $row->waitText }}@endif</td>
    @endif
    @if ($listVm->showComments)
    <td class="rowfollow nxm-td-comments" data-label="{{ 'Com' }}">
        @if ($row->comments === 0)
            <a href="comment.php?action=add&amp;pid={{ $row->id }}&amp;type=torrent" title="{{ __('legacy/functions.title_add_comments') }}">0</a>
        @else
            <b><a href="{{ $row->commentsUrl }}"@if ($row->lastCommentTooltipId) data-domtt-src="{{ $row->lastCommentTooltipId }}"@endif>@if ($row->commentIsNew)<span class="new">@endif{{ $row->comments }}@if ($row->commentIsNew)</span>@endif</a></b>
        @endif
    </td>
    @endif
    <td class="rowfollow nowrap nxm-td-added"><x-time :value="$row->added" :ago="false" :two-line="true" /></td>
    <td class="rowfollow nxm-td-size">{{ $row->size['value'] }}<br /> {{ $row->size['unit'] }}</td>
    <td class="rowfollow nxm-td-seeders" align="center" data-label="{{ 'S' }}">
        @if ($row->seedersUrl)
            <b><a href="{{ $row->seedersUrl }}">@if ($row->seedersClass)<span class="{{ $row->seedersClass }}">{{ number_format($row->seeders) }}</span>@else{{ number_format($row->seeders) }}@endif</a></b>
        @else
            <span class="{{ $row->seedersZeroClass }}">{{ number_format($row->seeders) }}</span>
        @endif
    </td>
    <td class="rowfollow nxm-td-leechers" data-label="{{ 'L' }}">@if ($row->leechersUrl)<b><a href="{{ $row->leechersUrl }}">{{ number_format($row->leechers) }}</a></b>@else{{ $row->leechers }}@endif</td>
    <td class="rowfollow nxm-td-snatched" data-label="{{ 'Sn' }}">@if ($row->snatchedUrl)<a href="{{ $row->snatchedUrl }}"><b>{{ number_format($row->snatched) }}</b></a>@else{{ number_format($row->snatched) }}@endif</td>
    <td class="rowfollow nxm-td-uploader" align="center">
        @if ($row->uploaderAnonymous)
            <i>{{ __('legacy/functions.text_anonymous') }}</i>@if ($row->uploaderShowOwner)<br />@if ($row->uploaderName)({{ $row->uploaderName }})@else(<i>{{ __('legacy/functions.text_orphaned') }}</i>)@endif @endif
        @elseif ($row->uploaderName)
            {{ $row->uploaderName }}
        @else
            <i>{{ __('legacy/functions.text_orphaned') }}</i>
        @endif
    </td>
    @if ($row->staffEditUrl !== null)
    <td class="rowfollow nxm-td-staff">@if ($row->staffDeleteUrl !== null)<a href="{{ $row->staffDeleteUrl }}"><img class="staff_delete" src="pic/trans.gif" alt="D" title="{{ __('legacy/functions.text_delete') }}" /></a><br />@endif<a href="{{ $row->staffEditUrl }}"><img class="staff_edit" src="pic/trans.gif" alt="E" title="{{ __('legacy/functions.text_edit') }}" /></a></td>
    @endif
</tr>
@endforeach
</tbody>
</table>
@if ($listVm->showPromotionNote)
<p class="nxm-note" align="center">{{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/functions.text_promoted_torrents_note')) }}</p>
@endif
@if ($listVm->lastCommentTooltips !== [])
<div class="nx-hidden">
    @foreach ($listVm->lastCommentTooltips as $tip)<div id="{{ $tip['id'] }}">{{ $tip['content'] }}</div>@endforeach
</div>
@endif
