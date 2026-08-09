@props([
    'striped' => false,
    'bordered' => true,
    'hover' => false,
    'responsive' => true,
])

@php
$classes = 'min-w-full border-collapse text-sm text-nexus-text';
$classes .= $bordered ? ' border border-nexus-border' : '';
@endphp

@if ($responsive)
    <div class="overflow-x-auto">
@endif

<table {{ $attributes->merge(['class' => $classes]) }}>
    @if (isset($head))
        <thead class="bg-nexus-surface-alt text-nexus-text">
            <tr>
                {{ $head }}
            </tr>
        </thead>
    @endif

    <tbody @class(['divide-y divide-nexus-border' => $bordered])>
        {{ $body ?? $slot }}
    </tbody>
</table>

@if ($responsive)
    </div>
@endif
