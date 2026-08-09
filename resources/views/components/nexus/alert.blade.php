@props([
    'variant' => 'info',
    'title' => null,
])

@php
$borders = [
    'info' => 'border-l-nexus-primary',
    'success' => 'border-l-nexus-success',
    'warning' => 'border-l-nexus-warning',
    'danger' => 'border-l-nexus-danger',
];

$bg = [
    'info' => 'bg-nexus-surface',
    'success' => 'bg-nexus-surface',
    'warning' => 'bg-nexus-surface',
    'danger' => 'bg-nexus-surface',
];

$classes = 'border border-nexus-border border-l-4 p-4 ' . ($borders[$variant] ?? $borders['info']) . ' ' . ($bg[$variant] ?? $bg['info']);
@endphp

@php
$role = in_array($variant, ['danger', 'warning']) ? 'alert' : 'status';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} role="{{ $role }}" aria-live="polite">
    @if ($title)
        <h3 class="mb-1 font-semibold text-nexus-text">{{ $title }}</h3>
    @endif
    <div class="text-sm text-nexus-text">
        {{ $slot }}
    </div>
</div>
