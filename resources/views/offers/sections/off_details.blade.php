@php
/** @var array<string, mixed> $off_details */
/** @var array<string, mixed> $lang */
$lang_offers = $lang;
$d = $off_details;
@endphp
<h1 align="center" id="top">{{ $d['name'] }}</h1>
<table width="97%" cellspacing="0" cellpadding="5">
<tr><td class="rowhead" align="right">{{ $lang_offers['row_info'] ?? '' }}</td><td class="rowfollow" align="left">{{ $lang_offers['text_offered_by'] ?? '' }}{!! $d['offeredBy'] !!}{!! $d['offerTime'] !!}</td></tr>
<tr><td class="rowhead" align="right">{{ $lang_offers['row_status'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['status'] !!}</td></tr>
@if (! empty($d['allowRow']))
<tr><td class="rowhead" align="right">{{ $lang_offers['row_allow'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['allowRow'] !!}</td></tr>
@endif
@if (! empty($d['voteRow']))
<tr><td class="rowhead" align="right">{{ $lang_offers['row_vote'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['voteRow'] !!}</td></tr>
@endif
@if (! empty($d['voteResultsRow']))
<tr><td class="rowhead" align="right">{{ $lang_offers['row_vote_results'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['voteResultsRow'] !!}</td></tr>
@endif
@if (! empty($d['allowedNote']))
<tr><td class="rowhead" align="right">{{ $lang_offers['row_offer_allowed'] ?? '' }}</td><td class="rowfollow" align="left">{{ $d['allowedNote'] }}</td></tr>
@endif
<tr><td class="rowhead" align="right">{{ $lang_offers['row_action'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['editLink'] !!}{!! $d['deleteLink'] !!}{!! $d['reportLink'] !!}</td></tr>
@if (! empty($d['description']))
<tr><td class="rowhead" align="right">{{ $lang_offers['row_description'] ?? '' }}</td><td class="rowfollow" align="left">{!! $d['description'] !!}</td></tr>
@endif
</table>
{!! $d['commentbar'] !!}
@if ($d['commentCount'] === 0)
{!! $d['commentsHtml'] !!}
@else
{!! $d['commentsHtml'] !!}
@endif
{!! $d['quickComment'] !!}
{!! $d['commentbar'] !!}
