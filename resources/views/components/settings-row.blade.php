@props(['label', 'relation' => '', 'layout' => 'tr', 'for' => null])
@if ($layout === 'setrow')
    <div class="nx-setrow @if ($relation !== '')nx-grouprow {{ $relation }}@endif"@if ($relation !== '') relation="{{ $relation }}"@endif>
        @if ($for !== null)<label class="nx-setrow__label" for="{{ $for }}">{{ $label }}</label>@else<span class="nx-setrow__label">{{ $label }}</span>@endif
        <div class="nx-setrow__control">{{ $slot }}</div>
    </div>
@elseif ($layout === 'grid')
    @if ($relation !== '')<div class="nx-grouprow {{ $relation }}" relation="{{ $relation }}">@endif
    @if ($for !== null)<label class="nx-fhead whitespace-nowrap" for="{{ $for }}">{{ $label }}</label>@else<div class="nx-fhead whitespace-nowrap">{{ $label }}</div>@endif
    <div class="nx-fcell">{{ $slot }}</div>
    @if ($relation !== '')</div>@endif
@else
<tr @if ($relation !== '') relation="{{ $relation }}" class="{{ $relation }}" @endif>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">@if ($for !== null)<label for="{{ $for }}">{{ $label }}</label>@else{{ $label }}@endif</td>
    <td class="align-top px-2.5 py-1.5">{{ $slot }}</td>
</tr>
@endif
