<h2 align="left">{{ __('legacy/offers.text_offers_section')}}</h2>
<div class="nx-box">
{{ $list['rules'] ?? '' }}
@if (! empty($list['addOfferLink']))
{{ $list['addOfferLink'] }}
@endif
{{ $list['searchBox'] ?? '' }}
</div>
<br /><br />
{{ $list['tableHtml'] ?? '' }}
