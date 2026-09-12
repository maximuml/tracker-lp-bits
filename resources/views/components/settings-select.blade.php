@props(['label', 'name', 'options' => [], 'selected' => null, 'note' => null])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
        <select name="{{ $name }}">
            @foreach ($options as $val => $optionLabel)
                <option value="{{ $val }}"@if ((string) $selected === (string) $val) selected @endif>{{ $optionLabel }}</option>
            @endforeach
        </select>
        @if ($note !== null && $note !== '') {{ $note }}@endif
    </td>
</tr>
