@props(['label', 'relation' => '', 'layout' => 'tr'])
@if ($layout === 'grid')
    @if ($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif
    <div class="nx-fhead nx-nowrap">{{ $label }}</div>
    <div class="nx-fcell">{{ $slot }}</div>
    @if ($relation !== '')</div>@endif
@else
<tr @if ($relation !== '') relation="{{ $relation }}" @endif><td class="rowhead nowrap nx-va-top nx-align-right nx-w-1p">{{ $label }}</td><td class="rowfollow nx-va-top nx-w-99p">{{ $slot }}</td></tr>
@endif
