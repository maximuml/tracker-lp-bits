<h2 align="left">{{ $lang['text_offers_section'] ?? 'Offers' }}</h2>
<div class="nx-box">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['rules'] ?? ''))
@if (! empty($list['addOfferLink']))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['addOfferLink']))
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['searchBox'] ?? ''))
</div>
<br /><br />
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['tableHtml'] ?? ''))
