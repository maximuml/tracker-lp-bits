@php
/** @var array<string, mixed> $offer_vote */
/** @var array<string, mixed> $lang */
$lang_offers = $lang;
$v = $offer_vote;
@endphp
<h1 align=center>{{ $lang_offers['text_vote_results_for'] ?? 'Vote results for' }} <a href="offers.php?id={{ $v['offerId'] }}&off_details=1"><b>{{ $v['offerName'] }}</b></a></h1>
@if (! $v['hasVotes'])
<p align=center><b>{{ $v['noVotesNote'] }}</b></p>
@else
{!! $v['pagerTop'] !!}
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=colhead>{{ $lang_offers['col_user'] ?? 'User' }}</td><td class=colhead align=left>{{ $lang_offers['col_vote'] ?? 'Vote' }}</td></tr>
@foreach ($v['rows'] as $row)
<tr><td class=rowfollow>{!! $row['username'] !!}</td><td class=rowfollow align=left>{!! $row['vote'] !!}</td></tr>
@endforeach
</table>
{!! $v['pagerBottom'] !!}
@endif
