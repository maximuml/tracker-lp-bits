<table class="torrents" cellspacing="0" cellpadding="5" width="100%" data-nx="data">
<tr>
    <td class="colhead"><a href="{{ $table->sortCatUrl }}">{{ __('legacy/offers.col_type') }}</a></td>
    <td class="colhead" width="100%"><a href="{{ $table->sortNameUrl }}">{{ __('legacy/offers.col_title') }}</a></td>
    <td colspan="3" class="colhead"><a href="{{ $table->sortVResUrl }}">{{ __('legacy/offers.col_vote_results') }}</a></td>
    <td class="colhead"><a href="{{ $table->sortCommentsUrl }}"><img class="comments" src="pic/trans.gif" alt="comments" title="{{ __('legacy/offers.title_comment') }}" /></a></td>
    <td class="colhead"><a href="{{ $table->sortAddedUrl }}"><img class="time" src="pic/trans.gif" alt="time" title="{{ __('legacy/offers.title_time_added') }}" /></a></td>
    @if ($table->showTimeout)
    <td class="colhead">{{ __('legacy/offers.col_timeout') }}</td>
    @endif
    <td class="colhead">{{ __('legacy/offers.col_offered_by') }}</td>
    @if ($table->canManage)
    <td class="colhead">{{ __('legacy/offers.col_act') }}</td>
    @endif
</tr>
@foreach ($table->rows as $row)
<tr>
    <td class="rowfollow"><x-torrent.category-icon :icon="$row->categoryIcon" /></td>
    <td><a href="?id={{ $row->id }}&amp;off_details=1" title="{{ $row->fullName }}"><b>{{ $row->displayName }}</b></a>
        @if ($row->isNew)
        <b> (<span class="new">{{ __('legacy/offers.text_new') }}</span>)</b>
        @endif
        &nbsp;<b>[<span class="{{ $row->allowed->cssClass }}">{{ $row->allowed->label }}</span>]</b></td>
    <td class="rowfollow nowrap" align="center">
        @if ($row->voteResults === null)
        0
        @else
        <b><a href="{{ $row->voteResults->href }}" title="{{ __('legacy/offers.title_show_vote_details') }}"><span class="nx-color-green">{{ $row->voteResults->yeah }}</span> - <span class="nx-color-red">{{ $row->voteResults->against }}</span> = {{ $row->voteResults->yeah - $row->voteResults->against }}</a></b>
        @endif
    </td>
    <td class="rowfollow nowrap" @if (! $table->canAgainst) colspan="2" @endif><a href="?id={{ $row->id }}&amp;vote=yeah" title="{{ __('legacy/offers.title_i_want_this') }}"><span class="nx-color-green"><b>{{ __('legacy/offers.text_yep') }}</b></span></a></td>
    @if ($table->showAgainstCell)
    <td class="rowfollow nowrap" align="center"><a href="?id={{ $row->id }}&amp;vote=against" title="{{ __('legacy/offers.title_do_not_want_it') }}"><span class="nx-color-red"><b>{{ __('legacy/offers.text_nah') }}</b></span></a></td>
    @endif
    <td class="rowfollow">
        @if ($row->comment->count === 0)
        <a href="{{ $row->comment->href }}" title="{{ $row->comment->title }}">0</a>
        @else
        <b><a @if ($row->comment->title !== null) title="{{ $row->comment->title }}" @endif href="{{ $row->comment->href }}" @if ($row->comment->tooltipId !== null) data-domtt-src="{{ $row->comment->tooltipId }}" @endif>@if ($row->comment->hasNew)<span class="new">{{ $row->comment->count }}</span>@else{{ $row->comment->count }}@endif</a></b>
        @endif
    </td>
    <td class="rowfollow nowrap">{{ $row->addedTime }}</td>
    @if ($table->showTimeout)
    <td class="rowfollow nowrap">{{ $row->timeout }}</td>
    @endif
    <td class="rowfollow">{{ $row->offeredBy }}</td>
    @if ($table->canManage)
    <td class="rowfollow"><a href="?id={{ $row->id }}&amp;del_offer=1"><img class="staff_delete" src="pic/trans.gif" alt="D" title="{{ __('legacy/offers.title_delete') }}" /></a><br /><a href="?id={{ $row->id }}&amp;edit_offer=1"><img class="staff_edit" src="pic/trans.gif" alt="E" title="{{ __('legacy/offers.title_edit') }}" /></a></td>
    @endif
</tr>
@endforeach
</table>
{{ $table->pagerBottom }}
@if ($table->tooltips !== [])
<div class="nx-hidden">
    @foreach ($table->tooltips as $tooltip)
    <div id="{{ $tooltip->id }}">{{ $tooltip->content }}</div>
    @endforeach
</div>
@endif
