@props(['title', 'description' => null])
<div {{ $attributes->merge(['class' => 'mx-auto my-6 max-w-xl rounded-md border border-nxm-border bg-nxm-surface p-5 text-center']) }}>
    <p class="font-semibold text-nxm-text">{{ $title }}</p>
    @if ($description !== null && $description !== '')
        <p class="mt-1.5 text-nxm-muted">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
