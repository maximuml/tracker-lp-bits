@props(['label', 'name', 'value' => 'yes', 'note' => null, 'yesLabel' => 'Yes', 'noLabel' => 'No'])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
        <input type="radio" id="{{ $name }}yes" name="{{ $name }}"@if ($value === 'yes') checked @endif value="yes"> <label for="{{ $name }}yes">{{ $yesLabel }}</label>
        <input type="radio" id="{{ $name }}no" name="{{ $name }}"@if ($value === 'no') checked @endif value="no"> <label for="{{ $name }}no">{{ $noLabel }}</label>
        @if ($note !== null && $note !== '')<br>{{ $note }}@endif
    </td>
</tr>
