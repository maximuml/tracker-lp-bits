@props(['caption' => '', 'center' => true, 'captionAlign' => 'left'])
@if ((string) $caption !== '')<h2 class="{{ $captionAlign === 'center' ? 'text-center' : 'text-left' }}">{{ $caption }}</h2>@endif
<div class="nx-box{{ $center ? ' text-center' : '' }}">
{{ $slot }}</div>
