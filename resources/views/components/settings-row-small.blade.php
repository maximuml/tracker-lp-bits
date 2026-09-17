@props(['label', 'relation' => '', 'layout' => 'tr'])
@if ($layout === 'grid')
    @if ($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif
    <div class="nx-fhead nx-nowrap">{{ $label }}</div>
    <div class="nx-fcell">{{ $slot }}</div>
    @if ($relation !== '')</div>@endif
@else
<tr @if ($relation !== '') relation="{{ $relation }}" @endif><td width="1%" class="rowhead nowrap" valign="top" align="right">{{ $label }}</td><td width="99%" class="rowfollow" valign="top" align="left">{{ $slot }}</td></tr>
@endif
