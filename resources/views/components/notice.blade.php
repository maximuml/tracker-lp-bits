@props(['title' => null, 'subtitle' => null, 'tone' => 'info', 'body' => null])
<div {{ $attributes->merge(['class' => 'nx-notice-page nx-page nx-page--form']) }}>
    @if ($title !== null && $title !== '')
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
    @endif
    <div class="nx-notice nx-notice--{{ $tone }}">
        <span class="nx-notice__icon" aria-hidden="true">
            @if ($tone === 'success')
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 8.5 6 12 13.5 4"/></svg>
            @elseif ($tone === 'error')
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5 14.5 13.5H1.5L8 2.5Z"/><path d="M8 6.5v3.5"/><path d="M8 11.8v.2"/></svg>
            @else
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.5"/><path d="M8 7.5v3.5"/><path d="M8 5v.2"/></svg>
            @endif
        </span>
        <div class="nx-notice__content">
            @isset($bodyTitle)
                <h2 class="nx-notice__title">{{ $bodyTitle }}</h2>
            @endisset
            @if ($body !== null)
                {{ $body }}
            @else
                {{ $slot }}
            @endif
            @isset($actionsCard)
                <div class="nx-notice__actions">{{ $actionsCard }}</div>
            @endisset
        </div>
    </div>
</div>
