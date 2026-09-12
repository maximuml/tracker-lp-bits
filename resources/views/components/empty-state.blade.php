@props(['title', 'description' => null])
<div {{ $attributes->merge(['class' => 'nx-empty']) }}>
    <p class="nx-empty__title">{{ $title }}</p>
    @if ($description !== null && $description !== '')
        <p class="nx-empty__desc">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="nx-empty__actions">{{ $slot }}</div>
    @endif
</div>
