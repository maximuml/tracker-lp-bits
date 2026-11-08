@props(['title', 'subtitle' => null, 'width' => 'list'])
<div {{ $attributes->merge(['class' => 'nx-page nx-page--'.($width === 'form' ? 'form' : 'list')]) }}>
    <div class="nx-page__head">
        <div class="nx-page__headtext">
            <h1 class="nx-page__title">{{ $title }}</h1>
            @if ($subtitle !== null && $subtitle !== '')
                <p class="nx-page__sub">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="nx-page__actions">{{ $actions }}</div>
        @endisset
    </div>
    @isset($tabs)
        {{ $tabs }}
    @endisset
    {{ $slot }}
</div>
