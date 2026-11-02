@props(['label', 'relation' => '', 'layout' => 'tr'])
@if ($layout === 'setrow')
    <div class="nx-setrow @if ($relation !== '')nx-grouprow {{ $relation }}@endif"@if ($relation !== '') relation="{{ $relation }}"@endif>
        <span class="nx-setrow__label">{{ $label }}</span>
        <div class="nx-setrow__control">{{ $slot }}</div>
    </div>
@elseif ($layout === 'grid')
    @if ($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif
    <div class="nx-fhead whitespace-nowrap">{{ $label }}</div>
    <div class="nx-fcell">{{ $slot }}</div>
    @if ($relation !== '')</div>@endif
@else
<tr @if ($relation !== '') relation="{{ $relation }}" class="{{ $relation }}" @endif>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td>
    <td class="align-top px-2.5 py-1.5">{{ $slot }}</td>
</tr>
@endif
