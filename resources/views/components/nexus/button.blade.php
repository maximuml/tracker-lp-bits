@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

@php
$base = 'inline-flex items-center justify-center font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-nexus-primary focus:ring-offset-2';

$sizes = [
    'sm' => 'px-2 py-1 text-xs',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
];

$variants = [
    'primary' => 'bg-nexus-primary text-nexus-primary-text hover:opacity-90',
    'secondary' => 'border border-nexus-border bg-nexus-surface-alt text-nexus-text hover:bg-nexus-bg',
    'danger' => 'bg-nexus-danger text-white hover:opacity-90',
    'success' => 'bg-nexus-success text-white hover:opacity-90',
    'warning' => 'bg-nexus-warning text-white hover:opacity-90',
    'ghost' => 'text-nexus-link hover:underline',
];

$classes = $base . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href && !$disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
