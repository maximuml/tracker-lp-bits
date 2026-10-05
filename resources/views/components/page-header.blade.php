@props(['title', 'subtitle' => null])
<header class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
    <h1 class="m-0 text-lg font-bold">@safeHtml($title)</h1>
    @if ($subtitle !== null && $subtitle !== '')
        <p class="m-0 text-nxm-text-dim">{{ $subtitle }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="ml-auto">{{ $slot }}</div>
    @endif
</header>
