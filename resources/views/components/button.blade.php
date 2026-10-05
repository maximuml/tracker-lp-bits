@props(['type' => 'button', 'variant' => 'default', 'href' => null])
@php
$variantClass = [
    'primary' => 'border-nxm-accent bg-nxm-accent text-nxm-on-accent hover:bg-nxm-accent-hover',
    'danger' => 'border-nxm-danger bg-nxm-danger text-nxm-on-danger hover:bg-nxm-danger',
][$variant] ?? 'border-nxm-border bg-nxm-surface-alt text-nxm-text hover:bg-nxm-border';
$baseClass = 'inline-block cursor-pointer rounded-[3px] border px-3.5 py-1 text-xs no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nxm-accent '.$variantClass;
@endphp
@if ($href !== null)
    <a {{ $attributes->merge(['class' => $baseClass]) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $baseClass]) }} type="{{ $type }}">{{ $slot }}</button>
@endif
