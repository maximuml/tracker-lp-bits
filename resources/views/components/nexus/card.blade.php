@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'border border-nexus-border bg-nexus-surface']) }}>
    @if ($title)
        <div class="border-b border-nexus-border bg-nexus-surface-alt px-4 py-2 font-semibold text-nexus-text">
            {{ $title }}
        </div>
    @endif

    <div class="p-4 text-nexus-text">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="border-t border-nexus-border bg-nexus-surface-alt px-4 py-2">
            {{ $footer }}
        </div>
    @endif
</div>
