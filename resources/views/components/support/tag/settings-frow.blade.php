@props(['relation', 'head', 'cell'])
<div class="nx-setrow nx-grouprow {{ $relation }}"@if($relation !== '') relation="{{ $relation }}"@endif><span class="nx-setrow__label">{{ $head }}</span><div class="nx-setrow__control">{{ $cell }}</div></div>
