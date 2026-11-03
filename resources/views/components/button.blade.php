@props(['type' => 'button', 'variant' => 'default', 'size' => null, 'href' => null])
@if ($href !== null)
    <a {{ $attributes->merge(['class' => 'nx-btn'.(['primary' => ' nx-btn--primary', 'danger' => ' nx-btn--danger'][$variant] ?? '').($size === 'sm' ? ' nx-btn--sm' : '')]) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => 'nx-btn'.(['primary' => ' nx-btn--primary', 'danger' => ' nx-btn--danger'][$variant] ?? '').($size === 'sm' ? ' nx-btn--sm' : '')]) }} type="{{ $type }}">{{ $slot }}</button>
@endif
