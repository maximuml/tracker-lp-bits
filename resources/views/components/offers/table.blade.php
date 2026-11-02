<x-data-table :caption="__('offers.head_offers')" captionHidden class="torrents nx-offtable">
<x-slot:head>
<thead>
<tr>
    <th class="bg-nxm-surface-alt font-semibold nx-offtable__type" scope="col"><a href="{{ $table->sortCatUrl }}">{{ __('offers.col_type') }}</a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortNameUrl }}">{{ __('offers.col_title') }}</a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortVResUrl }}">{{ __('offers.col_vote_results') }}</a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortCommentsUrl }}"><i class="comments" role="img" aria-label="comments" title="{{ __('offers.title_comment') }}"></i></a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortAddedUrl }}"><i class="time" role="img" aria-label="time" title="{{ __('offers.title_time_added') }}"></i></a></th>
    @if ($table->showTimeout)
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('offers.col_timeout') }}</th>
    @endif
    @if ($table->canManage)
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('offers.col_act') }}</th>
    @endif
</tr>
</thead>
</x-slot:head>

@foreach ($table->rows as $row)
<tr>
    <td class="nx-offtable__type px-2.5 py-1.5"><x-torrent.category-icon :icon="$row->categoryIcon" /></td>
    <td class="nx-offtable__title px-2.5 py-1.5"><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;off_details=1" title="{{ $row->fullName }}"><b>{{ $row->displayName }}</b></a>
        @if ($row->isNew)
        <b> (<span class="new">{{ __('offers.text_new') }}</span>)</b>
        @endif
        <span class="nx-off__chip nx-off__chip--{{ $row->allowed->variant }}">{{ $row->allowed->label }}</span>
        <div class="nx-off__meta">{{ $row->categoryIcon->name }} · {{ __('offers.text_by') }} {{ $row->offeredBy }}</div></td>
    <td class="nx-offtable__votes px-2.5 py-1.5">
        @if ($row->voteResults === null)
        <span class="nx-off__score">0</span>
        @else
        @php($score = $row->voteResults->yeah - $row->voteResults->against)
        @php($total = $row->voteResults->yeah + $row->voteResults->against)
        <div class="nx-off__scoreline"><a class="nx-off__score" href="{{ $row->voteResults->href }}" title="{{ __('offers.title_show_vote_details') }}"><span class="text-nxm-success">{{ $row->voteResults->yeah }}</span>/<span class="text-nxm-danger">{{ $row->voteResults->against }}</span> <b>{{ $score >= 0 ? '+'.$score : $score }}</b></a></div>
        <div class="nx-off__bar"><i style="width: {{ $total > 0 ? (int) round($row->voteResults->yeah / $total * 100) : 0 }}%"></i></div>
        @if ($score < $table->minVotes)
        <div class="nx-off__meta">{{ __('offers.more_to_approve', ['count' => $table->minVotes - $score]) }}</div>
        @else
        <div class="nx-off__meta">{{ __('offers.approved_at', ['count' => $table->minVotes]) }}</div>
        @endif
        <div class="nx-off__voting">
            <form method="post" action="/web/offers/vote" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $row->id }}" /><input type="hidden" name="vote" value="yeah" /><button type="submit" class="nx-btn-link" title="{{ __('offers.title_i_want_this') }}"><span class="text-nxm-success"><b>{{ __('offers.text_yep') }}</b></span></button></form>
            @if ($table->showAgainstCell)
            · <form method="post" action="/web/offers/vote" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $row->id }}" /><input type="hidden" name="vote" value="against" /><button type="submit" class="nx-btn-link" title="{{ __('offers.title_do_not_want_it') }}"><span class="text-nxm-danger"><b>{{ __('offers.text_nah') }}</b></span></button></form>
            @endif
        </div>
        @endif
    </td>
    <td class="nx-offtable__com px-2.5 py-1.5">
        @if ($row->comment->count === 0)
        <a href="{{ $row->comment->href }}" title="{{ $row->comment->title }}">0</a>
        @else
        <b><a @if ($row->comment->title !== null) title="{{ $row->comment->title }}" @endif href="{{ $row->comment->href }}" @if ($row->comment->tooltipId !== null) data-domtt-src="{{ $row->comment->tooltipId }}" @endif>@if ($row->comment->hasNew)<span class="new">{{ $row->comment->count }}</span>@else{{ $row->comment->count }}@endif</a></b>
        @endif
    </td>
    <td class="nx-offtable__added px-2.5 py-1.5 whitespace-nowrap">{{ $row->addedTime }}</td>
    @if ($table->showTimeout)
    <td class="nx-offtable__to px-2.5 py-1.5 whitespace-nowrap">{{ $row->timeout }}</td>
    @endif
    @if ($table->canManage)
    <td class="nx-offtable__act px-2.5 py-1.5"><form method="post" action="/web/offers/delete" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $row->id }}" /><button type="submit" class="nxm-linkbtn" title="{{ __('offers.title_delete') }}"><i class="staff_delete" role="img" aria-label="D" title="{{ __('offers.title_delete') }}"></i></button></form><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;edit_offer=1"><i class="staff_edit" role="img" aria-label="E" title="{{ __('offers.title_edit') }}"></i></a></td>
    @endif
</tr>
@endforeach
</x-data-table>
{{ $table->pagerBottom }}
@if ($table->tooltips !== [])
<div class="nx-hidden">
    @foreach ($table->tooltips as $tooltip)
    <div id="{{ $tooltip->id }}">{{ $tooltip->content }}</div>
    @endforeach
</div>
@endif
