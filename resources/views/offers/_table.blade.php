<x-data-table :caption="__('legacy/offers.head_offers')" captionHidden class="torrents">
<x-slot:head>
<thead>
<tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortCatUrl }}">{{ __('legacy/offers.col_type') }}</a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortNameUrl }}">{{ __('legacy/offers.col_title') }}</a></th>
    <th colspan="3" class="bg-nxm-surface-alt font-semibold" scope="colgroup"><a href="{{ $table->sortVResUrl }}">{{ __('legacy/offers.col_vote_results') }}</a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortCommentsUrl }}"><img class="comments" src="pic/trans.gif" alt="comments" title="{{ __('legacy/offers.title_comment') }}" /></a></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><a href="{{ $table->sortAddedUrl }}"><img class="time" src="pic/trans.gif" alt="time" title="{{ __('legacy/offers.title_time_added') }}" /></a></th>
    @if ($table->showTimeout)
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/offers.col_timeout') }}</th>
    @endif
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/offers.col_offered_by') }}</th>
    @if ($table->canManage)
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/offers.col_act') }}</th>
    @endif
</tr>
</thead>
</x-slot:head>

@foreach ($table->rows as $row)
<tr>
    <td class="align-top px-2.5 py-1.5"><x-torrent.category-icon :icon="$row->categoryIcon" /></td>
    <td><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;off_details=1" title="{{ $row->fullName }}"><b>{{ $row->displayName }}</b></a>
        @if ($row->isNew)
        <b> (<span class="new">{{ __('legacy/offers.text_new') }}</span>)</b>
        @endif
        &nbsp;<b>[<span class="{{ $row->allowed->cssClass }}">{{ $row->allowed->label }}</span>]</b></td>
    <td class="align-top px-2.5 py-1.5 whitespace-nowrap text-center">
        @if ($row->voteResults === null)
        0
        @else
        <b><a href="{{ $row->voteResults->href }}" title="{{ __('legacy/offers.title_show_vote_details') }}"><span class="text-nxm-success">{{ $row->voteResults->yeah }}</span> - <span class="text-nxm-danger">{{ $row->voteResults->against }}</span> = {{ $row->voteResults->yeah - $row->voteResults->against }}</a></b>
        @endif
    </td>
    <td class="align-top px-2.5 py-1.5 whitespace-nowrap" @if (! $table->canAgainst) colspan="2" @endif><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;vote=yeah" title="{{ __('legacy/offers.title_i_want_this') }}"><span class="text-nxm-success"><b>{{ __('legacy/offers.text_yep') }}</b></span></a></td>
    @if ($table->showAgainstCell)
    <td class="align-top px-2.5 py-1.5 whitespace-nowrap text-center"><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;vote=against" title="{{ __('legacy/offers.title_do_not_want_it') }}"><span class="text-nxm-danger"><b>{{ __('legacy/offers.text_nah') }}</b></span></a></td>
    @endif
    <td class="align-top px-2.5 py-1.5">
        @if ($row->comment->count === 0)
        <a href="{{ $row->comment->href }}" title="{{ $row->comment->title }}">0</a>
        @else
        <b><a @if ($row->comment->title !== null) title="{{ $row->comment->title }}" @endif href="{{ $row->comment->href }}" @if ($row->comment->tooltipId !== null) data-domtt-src="{{ $row->comment->tooltipId }}" @endif>@if ($row->comment->hasNew)<span class="new">{{ $row->comment->count }}</span>@else{{ $row->comment->count }}@endif</a></b>
        @endif
    </td>
    <td class="align-top px-2.5 py-1.5 whitespace-nowrap">{{ $row->addedTime }}</td>
    @if ($table->showTimeout)
    <td class="align-top px-2.5 py-1.5 whitespace-nowrap">{{ $row->timeout }}</td>
    @endif
    <td class="align-top px-2.5 py-1.5">{{ $row->offeredBy }}</td>
    @if ($table->canManage)
    <td class="align-top px-2.5 py-1.5"><form method="post" action="/web/offers/delete" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $row->id }}" /><button type="submit" class="nxm-linkbtn" title="{{ __('legacy/offers.title_delete') }}"><img class="staff_delete" src="pic/trans.gif" alt="D" title="{{ __('legacy/offers.title_delete') }}" /></button></form><br /><a href="{{ request()->getPathInfo() }}?id={{ $row->id }}&amp;edit_offer=1"><img class="staff_edit" src="pic/trans.gif" alt="E" title="{{ __('legacy/offers.title_edit') }}" /></a></td>
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
