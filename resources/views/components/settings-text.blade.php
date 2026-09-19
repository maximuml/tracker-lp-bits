@props(['layout' => 'tr', 'label', 'name', 'value' => '', 'note' => null])
@if ($layout === 'grid')
<div class="nx-fhead nx-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
@endif
        <input type="text" name="{{ $name }}" value="{{ (string) $value }}">
        @if ($note !== null && $note !== '') {{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
