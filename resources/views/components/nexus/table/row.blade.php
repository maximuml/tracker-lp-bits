@props([
    'striped' => false,
    'hover' => false,
])

@php
$classes = '';
$classes .= $striped ? ' even:bg-nexus-surface' : '';
$classes .= $hover ? ' hover:bg-nexus-surface-alt' : '';
@endphp

<tr {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</tr>
