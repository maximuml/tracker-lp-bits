@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'selected' => null, 'note' => null])
@if ($layout === 'grid')
<div class="nx-fhead nx-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td>
@endif
        <select name="{{ $name }}">
            @foreach ($options as $val => $optionLabel)
                <option value="{{ $val }}"@if ((string) $selected === (string) $val) selected @endif>{{ $optionLabel }}</option>
            @endforeach
        </select>
        @if ($note !== null && $note !== '') {{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
