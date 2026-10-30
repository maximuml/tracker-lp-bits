@if ($off_details)
<h1 class="text-center" id="top">{{ $off_details->name }}</h1>
<x-data-table :caption="$off_details->name" captionHidden class="w-[97%]">
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_info')}}</td><td class="align-top px-2.5 py-1.5">{{ __('offers.text_offered_by')}}{{ $off_details->offeredBy }}{{ $off_details->offerTime }}</td></tr>
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_status')}}</td><td class="align-top px-2.5 py-1.5"><span class="{{ $off_details->status->cssClass }}">{{ $off_details->status->label }}</span></td></tr>
@if ($off_details->showAllowRow)
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_allow')}}</td><td class="align-top px-2.5 py-1.5"><div class="nxm-offer-actions"><form method="post" action="/web/offers/allow">@csrf<input type="hidden" value="{{ $off_details->id }}" name="offerid" /><input class="nx-btn nx-btn--primary" type="submit" value="{{ __('offers.submit_allow') }}" />&nbsp;&nbsp;</form><form method="post" action="/web/offers/finish">@csrf<input type="hidden" value="{{ $off_details->id }}" name="finish" /><input class="nx-btn nx-btn--primary" type="submit" value="{{ __('offers.submit_let_votes_decide') }}" /></form></div></td></tr>
@endif
@if ($off_details->isPending)
<livewire:offer-vote :offer-id="$off_details->id" :can-against="$off_details->canAgainst" :yeah="$off_details->yeah" :against="$off_details->against" />
@endif
@if ($off_details->allowedNote !== '')
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_offer_allowed')}}</td><td class="align-top px-2.5 py-1.5">{{ $off_details->allowedNote }}</td></tr>
@endif
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_action')}}</td><td class="align-top px-2.5 py-1.5">@if ($off_details->showEditDelete)<a href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;edit_offer=1"><i class="dt_edit" role="img" aria-label="edit"></i>&nbsp;<b><span class="small">{{ __('offers.text_edit_offer') }}</span></b></a>&nbsp;|&nbsp;<a href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;del_offer=1&amp;sure=0"><i class="dt_delete" role="img" aria-label="delete"></i>&nbsp;<b><span class="small">{{ __('offers.text_delete_offer') }}</span></b></a>&nbsp;|&nbsp;@endif<a href="/web/report?reportofferid={{ $off_details->id }}"><i class="dt_report" role="img" aria-label="report"></i>&nbsp;<b><span class="small">{{ __('offers.report_offer') }}</span></b></a></td></tr>
@if ((string) $off_details->description !== '')
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_description')}}</td><td class="align-top px-2.5 py-1.5">{{ $off_details->description }}</td></tr>
@endif
</x-data-table>
<p class="text-center"><a class="index" href="/comment/add?pid={{ $off_details->id }}&amp;type=offer">{{ __('offers.text_add_comment') }}</a></p>
@if (! $off_details->commentCount)
<h1 id="startcomments" class="text-center">{{ __('offers.text_no_comments') }}</h1>
@else
{{ $off_details->pagerTop }}
@endif
<livewire:comment-section :parent-id="$off_details->id" type="offer" />
@if ($off_details->commentCount)
{{ $off_details->pagerBottom }}
@endif
<p class="text-center"><a class="index" href="/comment/add?pid={{ $off_details->id }}&amp;type=offer">{{ __('offers.text_add_comment') }}</a></p>
@endif
