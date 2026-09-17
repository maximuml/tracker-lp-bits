<h1 align="center" id="top">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['name'] ?? ''))</h1>
<table data-nx="data" width="97%" cellspacing="0" cellpadding="5">
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_info')}}</td><td class="rowfollow" align="left">{{ __('legacy/offers.text_offered_by')}}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['offeredBy'] ?? ''))@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['offerTime'] ?? ''))</td></tr>
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_status')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['status'] ?? ''))</td></tr>
@if (! empty($off_details['allowRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_allow')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['allowRow']))</td></tr>
@endif
@if (! empty($off_details['voteRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_vote')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['voteRow']))</td></tr>
@endif
@if (! empty($off_details['voteResultsRow']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_vote_results')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['voteResultsRow']))</td></tr>
@endif
@if (! empty($off_details['allowedNote']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_offer_allowed')}}</td><td class="rowfollow" align="left">{{ $off_details['allowedNote'] }}</td></tr>
@endif
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_action')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['editLink'] ?? ''))@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['deleteLink'] ?? ''))@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['reportLink'] ?? ''))</td></tr>
@if (! empty($off_details['description']))
<tr><td class="rowhead" align="right">{{ __('legacy/offers.row_description')}}</td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['description']))</td></tr>
@endif
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['commentbar'] ?? ''))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['commentsHtml'] ?? ''))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['quickComment'] ?? ''))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($off_details['commentbar'] ?? ''))
