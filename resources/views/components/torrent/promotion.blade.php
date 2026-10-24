@props(['badge'])
@if ($badge->mode === 'word')
 <b>[<span class="{{ $badge->cssClass }}"@if ($badge->domtt !== null) data-domtt-promo @endif>{{ $badge->text }}</span>@if ($badge->domtt !== null)<template class="nx-tt"><b><span class="{{ $badge->domtt->cssClass }}">{{ $badge->domtt->text }}</span></b>{{ $badge->domtt->endIn }}<b>{{ $badge->domtt->timeout }}</b></template>@endif]</b>
@else
 <span class="nx-promo nx-promo--{{ $badge->cssClass }}" role="img" aria-label="{{ $badge->alt }}"@if ($badge->domtt !== null) data-domtt-promo @else title="{{ $badge->text }}"@endif>{{ $badge->alt }}</span>@if ($badge->domtt !== null)<template class="nx-tt"><b><span class="{{ $badge->domtt->cssClass }}">{{ $badge->domtt->text }}</span></b>{{ $badge->domtt->endIn }}<b>{{ $badge->domtt->timeout }}</b></template>@endif
@endif
@if ($badge->timeout !== null)
    @if ($badge->subColor !== null) <span class="{{ $badge->subColor }}">{{ __('functions.text_will_end_in') }}{{ $badge->timeout }}</span>@else {{ __('functions.text_will_end_in') }}{{ $badge->timeout }}@endif
@endif
