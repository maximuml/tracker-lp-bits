<h1 class="text-center">{{ __('legacy/offers.text_vote_results_for')}} <a href="/web/offers?id={{ $offer_vote['offerId'] }}&off_details=1"><b>{{ $offer_vote['offerName'] }}</b></a></h1>
@if (! $offer_vote['hasVotes'])
<p class="text-center"><b>{{ $offer_vote['noVotesNote'] }}</b></p>
@else
{{ $offer_vote['pagerTop'] ?? '' }}
<x-data-table :caption="__('legacy/offers.text_vote_results_for') . ' ' . $offer_vote['offerName']" captionHidden>
<x-slot:head>
<thead>
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/offers.col_user')}}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ __('legacy/offers.col_vote')}}</th></tr>
</thead>
</x-slot:head>

@foreach ($offer_vote['rows'] as $row)
<tr><td class="align-top px-2.5 py-1.5">{{ $row['username'] ?? '' }}</td><td class="align-top px-2.5 py-1.5">@if (($row['vote'] ?? '') === 'yeah')<b><span class="text-nxm-success">{{ __('legacy/offers.text_for') }}</span></b>@elseif (($row['vote'] ?? '') === 'against')<b><span class="text-nxm-danger">{{ __('legacy/offers.text_against') }}</span></b>@else{{ 'unknown' }}@endif</td></tr>
@endforeach
</x-data-table>
{{ $offer_vote['pagerBottom'] ?? '' }}
@endif
