@props(['label', 'relation' => '', 'layout' => 'tr'])
@if ($layout === 'grid')
    @if ($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif
    <div class="nx-fhead nx-nowrap">{{ $label }}</div>
    <div class="nx-fcell">{{ $slot }}</div>
    @if ($relation !== '')</div>@endif
@else
<tr @if ($relation !== '') relation="{{ $relation }}" class="{{ $relation }}" @endif>
    <td class="rowhead nowrap nx-va-top nx-align-right">{{ $label }}</td>
    <td class="rowfollow nx-va-top">{{ $slot }}</td>
</tr>
@endif
