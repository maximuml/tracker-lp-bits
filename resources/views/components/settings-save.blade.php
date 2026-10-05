@props(['layout' => 'tr', 'label', 'text' => 'Save'])
@if ($layout === 'grid')
<div class="nx-fhead whitespace-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td>
    <td class="align-top px-2.5 py-1.5">
@endif<input type="submit" name="save" value="{{ $text }}">@if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
