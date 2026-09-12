@props(['label', 'name', 'value' => '', 'note' => null, 'width' => '300px'])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
        <input type="text" style="width: {{ $width }}" name="{{ $name }}" value="{{ (string) $value }}">
        @if ($note !== null && $note !== '') {{ $note }}@endif
    </td>
</tr>
