<h1 align="center" id="top">{{ $off_details['name'] ?? '' }}</h1>
<table data-nx="data" width="97%" cellspacing="0" cellpadding="5">
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_info')}}</td><td class="rowfollow" align="left">{{ __('legacy/offers.text_offered_by')}}{{ $off_details['offeredBy'] ?? '' }}{{ $off_details['offerTime'] ?? '' }}</td></tr>
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_status')}}</td><td class="rowfollow" align="left">{{ $off_details['status'] ?? '' }}</td></tr>
@if (! empty($off_details['allowRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_allow')}}</td><td class="rowfollow" align="left">{{ $off_details['allowRow'] }}</td></tr>
@endif
@if (! empty($off_details['voteRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_vote')}}</td><td class="rowfollow" align="left">{{ $off_details['voteRow'] }}</td></tr>
@endif
@if (! empty($off_details['voteResultsRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_vote_results')}}</td><td class="rowfollow" align="left">{{ $off_details['voteResultsRow'] }}</td></tr>
@endif
@if (! empty($off_details['allowedNote']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_offer_allowed')}}</td><td class="rowfollow" align="left">{{ $off_details['allowedNote'] }}</td></tr>
@endif
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_action')}}</td><td class="rowfollow" align="left">{{ $off_details['editLink'] ?? '' }}{{ $off_details['deleteLink'] ?? '' }}{{ $off_details['reportLink'] ?? '' }}</td></tr>
@if (! empty($off_details['description']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_description')}}</td><td class="rowfollow" align="left">{{ $off_details['description'] }}</td></tr>
@endif
</table>
{{ $off_details['commentbar'] ?? '' }}
{{ $off_details['commentsHtml'] ?? '' }}
{{ $off_details['quickComment'] ?? '' }}
{{ $off_details['commentbar'] ?? '' }}
