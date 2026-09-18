<h1 align=center>{{ __('legacy/offers.text_vote_results_for')}} <a href="offers.php?id={{ $offer_vote['offerId'] }}&off_details=1"><b>{{ $offer_vote['offerName'] }}</b></a></h1>
@if (! $offer_vote['hasVotes'])
<p align=center><b>{{ $offer_vote['noVotesNote'] }}</b></p>
@else
{{ $offer_vote['pagerTop'] ?? '' }}
<table data-nx="data" border=1 cellspacing=0 cellpadding=5>
<tr><td class=colhead>{{ __('legacy/offers.col_user')}}</td><td class=colhead align=left>{{ __('legacy/offers.col_vote')}}</td></tr>
@foreach ($offer_vote['rows'] as $row)
<tr><td class=rowfollow>{{ $row['username'] ?? '' }}</td><td class=rowfollow align=left>{{ $row['vote'] ?? '' }}</td></tr>
@endforeach
</table>
{{ $offer_vote['pagerBottom'] ?? '' }}
@endif
