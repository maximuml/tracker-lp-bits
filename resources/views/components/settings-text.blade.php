@props(['layout' => 'tr', 'label', 'name', 'value' => '', 'note' => null, 'size' => null])
@php($fieldId = 'set-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name))
@if ($layout === 'grid')
<label class="nx-fhead whitespace-nowrap" for="{{ $fieldId }}">{{ $label }}</label>
<div class="nx-fcell">
@else
<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim"><label for="{{ $fieldId }}">{{ $label }}</label></td>
    <td>
@endif
        <input type="text" id="{{ $fieldId }}" name="{{ $name }}" value="{{ (string) $value }}"@if ($size !== null) size="{{ $size }}"@endif>
        @if ($note !== null && $note !== '') {{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
