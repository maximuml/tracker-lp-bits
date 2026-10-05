@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'selected' => null, 'note' => null])
@if ($layout === 'grid')
<div class="nx-fhead whitespace-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td>
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
