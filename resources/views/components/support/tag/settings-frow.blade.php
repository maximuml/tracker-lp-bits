@props(['relation', 'head', 'cell'])
@if($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif<div class="nx-fhead whitespace-nowrap">{{ $head }}</div><div class="nx-fcell">{{ $cell }}</div>@if($relation !== '')</div>@endif
