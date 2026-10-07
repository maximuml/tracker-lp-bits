@if ($off_details)
<h1 class="text-center" id="top">{{ $off_details->name }}</h1>
<x-data-table :caption="$off_details->name" captionHidden class="w-[97%]">
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_info')}}</td><td class="align-top px-2.5 py-1.5">{{ __('legacy/offers.text_offered_by')}}{{ $off_details->offeredBy }}{{ $off_details->offerTime }}</td></tr>
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_status')}}</td><td class="align-top px-2.5 py-1.5"><span class="{{ $off_details->status->cssClass }}">{{ $off_details->status->label }}</span></td></tr>
@if ($off_details->showAllowRow)
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_allow')}}</td><td class="align-top px-2.5 py-1.5"><div class="nxm-offer-actions"><form method="post" action="/web/offers/allow">@csrf<input type="hidden" value="{{ $off_details->id }}" name="offerid" /><input class="btn" type="submit" value="{{ __('legacy/offers.submit_allow') }}" />&nbsp;&nbsp;</form><form method="post" action="/web/offers/finish">@csrf<input type="hidden" value="{{ $off_details->id }}" name="finish" /><input class="btn" type="submit" value="{{ __('legacy/offers.submit_let_votes_decide') }}" /></form></div></td></tr>
@endif
@if ($off_details->isPending)
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_vote')}}</td><td class="align-top px-2.5 py-1.5"><b><form method="post" action="/web/offers/vote" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $off_details->id }}" /><input type="hidden" name="vote" value="yeah" /><button type="submit" class="nx-btn-link"><span class="text-nxm-success">{{ __('legacy/offers.text_for') }}</span></button></form></b>@if ($off_details->canAgainst) - <b><form method="post" action="/web/offers/vote" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $off_details->id }}" /><input type="hidden" name="vote" value="against" /><button type="submit" class="nx-btn-link"><span class="text-nxm-danger">{{ __('legacy/offers.text_against') }}</span></button></form></b>@endif</td></tr>
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_vote_results')}}</td><td class="align-top px-2.5 py-1.5"><b>{{ __('legacy/offers.text_for') }}:</b> {{ $off_details->yeah }}  <b>{{ __('legacy/offers.text_against') }}</b> {{ $off_details->against }} &nbsp; &nbsp; <a href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;offer_vote=1"><i>{{ __('legacy/offers.text_see_vote_detail') }}</i></a></td></tr>
@endif
@if ($off_details->allowedNote !== '')
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_offer_allowed')}}</td><td class="align-top px-2.5 py-1.5">{{ $off_details->allowedNote }}</td></tr>
@endif
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_action')}}</td><td class="align-top px-2.5 py-1.5">@if ($off_details->showEditDelete)<a href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;edit_offer=1"><img class="dt_edit" src="pic/trans.gif" alt="edit" />&nbsp;<b><span class="small">{{ __('legacy/offers.text_edit_offer') }}</span></b></a>&nbsp;|&nbsp;<a href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;del_offer=1&amp;sure=0"><img class="dt_delete" src="pic/trans.gif" alt="delete" />&nbsp;<b><span class="small">{{ __('legacy/offers.text_delete_offer') }}</span></b></a>&nbsp;|&nbsp;@endif<a href="/web/report?reportofferid={{ $off_details->id }}"><img class="dt_report" src="pic/trans.gif" alt="report" />&nbsp;<b><span class="small">{{ __('legacy/offers.report_offer') }}</span></b></a></td></tr>
@if ((string) $off_details->description !== '')
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/offers.row_description')}}</td><td class="align-top px-2.5 py-1.5">{{ $off_details->description }}</td></tr>
@endif
</x-data-table>
<p class="text-center"><a class="index" href="/comment/add?amp;pid={{ $off_details->id }}&amp;type=offer">{{ __('legacy/offers.text_add_comment') }}</a></p>
@if (! $off_details->commentCount)
<h1 id="startcomments" class="text-center">{{ __('legacy/offers.text_no_comments') }}</h1>
@else
{{ $off_details->pagerTop }}
@endif
<livewire:comment-section :parent-id="$off_details->id" type="offer" />
@if ($off_details->commentCount)
{{ $off_details->pagerBottom }}
@endif
<p class="text-center"><a class="index" href="/comment/add?amp;pid={{ $off_details->id }}&amp;type=offer">{{ __('legacy/offers.text_add_comment') }}</a></p>
@endif
