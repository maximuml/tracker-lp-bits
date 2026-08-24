@php
/** @var array<string, mixed> $list */
/** @var array<string, mixed> $lang */
$lang_offers = $lang;
$listData = $list;
@endphp
<h2 align="left">{{ $lang_offers['text_offers_section'] ?? 'Offers' }}</h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10">
<tr><td class="text">
{!! $listData['rules'] !!}
@if (! empty($listData['addOfferLink']))
{!! $listData['addOfferLink'] !!}
@endif
{!! $listData['searchBox'] !!}
</td></tr></table>
<br /><br />
@if (! $listData['hasRows'])
{!! $listData['tableHtml'] !!}
@else
{!! $listData['tableHtml'] !!}
@endif
