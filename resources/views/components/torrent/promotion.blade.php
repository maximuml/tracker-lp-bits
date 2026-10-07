@props(['badge'])
@if ($badge->mode === 'word')
 <b>[<span class="{{ $badge->cssClass }}"@if ($badge->domttHtml !== null) data-domtt-promo @endif>{{ $badge->text }}</span>@if ($badge->domttHtml !== null)<template class="nx-tt">{{ $badge->domttHtml }}</template>@endif]</b>
@else
 <span class="nx-promo nx-promo--{{ $badge->cssClass }}" role="img" aria-label="{{ $badge->alt }}"@if ($badge->domttHtml !== null) data-domtt-promo @else title="{{ $badge->text }}"@endif>{{ $badge->alt }}</span>@if ($badge->domttHtml !== null)<template class="nx-tt">{{ $badge->domttHtml }}</template>@endif
@endif
@if ($badge->timeout !== null)
    @if ($badge->subColor !== null) <span class="{{ $badge->subColor }}">{{ __('functions.text_will_end_in') }}{{ $badge->timeout }}</span>@else {{ __('functions.text_will_end_in') }}{{ $badge->timeout }}@endif
@endif
