@props(['caption'])
@if ((string) $caption !== '')<h2>{{ $caption }}</h2>@endif
<div class="nx-box nx-center">
<table class="main" data-nx="data"><caption class="nx-sr-only">{{ $caption }}</caption>{{ $slot }}</table>
</div>
