@props(['layout' => 'tr', 'label', 'name', 'value' => 'yes', 'note' => null, 'yesLabel' => 'Yes', 'noLabel' => 'No'])
@if ($layout === 'grid')
<div class="nx-fhead nx-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
@endif
        <input type="radio" id="{{ $name }}yes" name="{{ $name }}"@if ($value === 'yes') checked @endif value="yes"> <label for="{{ $name }}yes">{{ $yesLabel }}</label>
        <input type="radio" id="{{ $name }}no" name="{{ $name }}"@if ($value === 'no') checked @endif value="no"> <label for="{{ $name }}no">{{ $noLabel }}</label>
        @if ($note !== null && $note !== '')<br>{{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
