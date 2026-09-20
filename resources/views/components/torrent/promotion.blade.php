@props(['badge'])
@if ($badge->mode === 'word')
 <b>[<span class="{{ $badge->cssClass }}"@if ($badge->domttHtml !== null) data-domtt-promo@endif>{{ $badge->text }}</span>@if ($badge->domttHtml !== null)<template class="nx-tt">{{ $badge->domttHtml }}</template>@endif]</b>
@else
 <img class="{{ $badge->iconClass }}" src="pic/trans.gif" alt="{{ $badge->alt }}"@if ($badge->domttHtml !== null) data-domtt-promo@else title="{{ $badge->text }}"@endif />@if ($badge->domttHtml !== null)<template class="nx-tt">{{ $badge->domttHtml }}</template>@endif
@endif
@if ($badge->timeout !== null)
    @if ($badge->subColor !== null) <span class="{{ $badge->subColor }}">{{ __('legacy/functions.text_will_end_in') }}{{ $badge->timeout }}</span>@else {{ __('legacy/functions.text_will_end_in') }}{{ $badge->timeout }}@endif
@endif
