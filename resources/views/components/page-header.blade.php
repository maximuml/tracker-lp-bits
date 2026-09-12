@props(['title', 'subtitle' => null])
<header class="nx-page-header">
    <h1 class="nx-page-header__title">@safeHtml($title)</h1>
    @if ($subtitle !== null && $subtitle !== '')
        <p class="nx-page-header__subtitle">{{ $subtitle }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="nx-page-header__actions">{{ $slot }}</div>
    @endif
</header>
