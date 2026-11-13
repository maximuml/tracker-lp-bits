@props(['cls' => null])
{{-- Renders slot text wrapped in a tone class when one is computed
     (nx-ratio-N, nx-sl-N, red/green …); plain text otherwise. Replaces
     the repeated `@if ($x->ratioClass)<span class=…>@else…@endif` idiom. --}}
@if ($cls)
    <span class="{{ $cls }}">{{ $slot }}</span>
@else
    {{ $slot }}
@endif
