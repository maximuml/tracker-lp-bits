@props(['cls', 'alt', 'text', 'domtt'])
@if($domtt !== null) <span class="nx-promo nx-promo--{{ $cls }}" role="img" aria-label="{{ $alt }}" data-domtt-promo>{{ $alt }}<template class="nx-tt">{{ $domtt }}</template></span>@else <span class="nx-promo nx-promo--{{ $cls }}" role="img" aria-label="{{ $alt }}" title="{{ $text }}">{{ $alt }}</span>@endif
