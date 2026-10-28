@props(['linkClass', 'url', 'newWindow', 'text'])
<a{{ '' }}@if($linkClass !== '') class="{{ $linkClass }}"@endif href="{{ $url }}"@if($newWindow) target="_blank" rel="noopener noreferrer"@endif>{{ $text }}</a>
