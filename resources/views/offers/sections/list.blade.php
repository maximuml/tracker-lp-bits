<h2 align="left">{{ $lang['text_offers_section'] ?? 'Offers' }}</h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10">
<tr><td class="text">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['rules'] ?? ''))
@if (! empty($list['addOfferLink']))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['addOfferLink']))
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['searchBox'] ?? ''))
</td></tr></table>
<br /><br />
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($list['tableHtml'] ?? ''))
