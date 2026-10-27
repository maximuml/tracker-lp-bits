@props(['tag', 'isRainbow', 'inner'])
<{{ $tag }}@if($isRainbow) class="rainbow"@endif>{{ $inner }}</{{ $tag }}>
