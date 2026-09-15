@props(['layout' => 'tr', 'label', 'text' => 'Save'])
@if ($layout === 'grid')
<div class="nx-fhead nx-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td class="rowfollow" valign="top" align="left">
@endif<input type="submit" name="save" value="{{ $text }}">@if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
