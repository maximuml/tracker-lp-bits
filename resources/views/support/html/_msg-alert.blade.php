@props(['colorClass', 'url', 'text'])
<table class="msg-alert" data-nx="layout"><tr><td class="{{ $colorClass }}">
@if($url !== '')<b><a href="{{ $url }}" target='_blank'><span class="nx-color-white">{{ $text }}</span></a></b>@else<b><span class="nx-color-white">{{ $text }}</span></b>@endif</td></tr></table><br />
