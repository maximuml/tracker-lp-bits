<h1 class="nx-center">{{ __('legacy/offers.text_vote_results_for')}} <a href="offers.php?id={{ $offer_vote['offerId'] }}&off_details=1"><b>{{ $offer_vote['offerName'] }}</b></a></h1>
@if (! $offer_vote['hasVotes'])
<p class="nx-center"><b>{{ $offer_vote['noVotesNote'] }}</b></p>
@else
{{ $offer_vote['pagerTop'] ?? '' }}
<table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/offers.text_vote_results_for') }} {{ $offer_vote['offerName'] }}</caption>
<tr><th class="colhead" scope="col">{{ __('legacy/offers.col_user')}}</th><th class="colhead nx-align-left" scope="col">{{ __('legacy/offers.col_vote')}}</th></tr>
@foreach ($offer_vote['rows'] as $row)
<tr><td class=rowfollow>{{ $row['username'] ?? '' }}</td><td class="rowfollow">@if (($row['vote'] ?? '') === 'yeah')<b><span class="nx-color-green">{{ __('legacy/offers.text_for') }}</span></b>@elseif (($row['vote'] ?? '') === 'against')<b><span class="nx-color-red">{{ __('legacy/offers.text_against') }}</span></b>@else{{ 'unknown' }}@endif</td></tr>
@endforeach
</table>
{{ $offer_vote['pagerBottom'] ?? '' }}
@endif
