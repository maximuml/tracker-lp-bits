@props(['type' => 'button', 'variant' => 'default', 'href' => null])
@if ($href !== null)
    <a {{ $attributes->merge(['class' => 'nx-btn nx-btn--'.$variant]) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => 'nx-btn nx-btn--'.$variant]) }} type="{{ $type }}">{{ $slot }}</button>
@endif
