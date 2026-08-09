@props([
    'variant' => 'default',
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'px-1.5 py-0.5 text-xs',
    'md' => 'px-2 py-1 text-xs',
    'lg' => 'px-3 py-1.5 text-sm',
];

$variants = [
    'default' => 'bg-nexus-surface-alt text-nexus-text border border-nexus-border',
    'primary' => 'bg-nexus-primary text-nexus-primary-text',
    'success' => 'bg-nexus-success text-white',
    'warning' => 'bg-nexus-warning text-white',
    'danger' => 'bg-nexus-danger text-white',
];

$classes = 'inline-flex items-center rounded-sm font-medium ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['default']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
