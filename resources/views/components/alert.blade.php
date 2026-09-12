@props(['type' => 'info', 'title' => null])
<div {{ $attributes->merge(['class' => 'nx-alert nx-alert--'.$type]) }} role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}">
    @if ($title !== null && $title !== '')
        <p class="nx-alert__title">{{ $title }}</p>
    @endif
    <div class="nx-alert__body">{{ $slot }}</div>
</div>
