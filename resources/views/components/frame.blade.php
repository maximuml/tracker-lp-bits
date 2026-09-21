@props(['caption' => '', 'center' => true, 'captionAlign' => 'left'])
@if ((string) $caption !== '')<h2 class="{{ $captionAlign === 'center' ? 'nx-center' : 'nx-align-left' }}">{{ $caption }}</h2>@endif
<div class="nx-box{{ $center ? ' nx-center' : '' }}">
{{ $slot }}</div>
