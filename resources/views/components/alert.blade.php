@props(['type' => 'info', 'title' => null])
@php
$variant = [
    'success' => 'border-nxm-success-border bg-nxm-success-bg',
    'warning' => 'border-nxm-warning-border bg-nxm-warning-bg',
    'error' => 'border-nxm-danger-border bg-nxm-danger-bg',
][$type] ?? 'border-nxm-info-border bg-nxm-info-bg';
@endphp
<div {{ $attributes->merge(['class' => 'my-2 rounded border px-3 py-2 text-nxm-text [&_a]:text-nxm-accent-text '.$variant]) }} role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}">
    @if ($title !== null && $title !== '')
        <p class="mb-1 font-bold">{{ $title }}</p>
    @endif
    <div>{{ $slot }}</div>
</div>
