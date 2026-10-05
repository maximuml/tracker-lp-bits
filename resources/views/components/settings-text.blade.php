@props(['layout' => 'tr', 'label', 'name', 'value' => '', 'note' => null, 'size' => null])
@if ($layout === 'grid')
<div class="nx-fhead whitespace-nowrap">{{ $label }}</div>
<div class="nx-fcell">
@else
<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td>
    <td>
@endif
        <input type="text" name="{{ $name }}" value="{{ (string) $value }}"@if ($size !== null) size="{{ $size }}"@endif>
        @if ($note !== null && $note !== '') {{ $note }}@endif
    @if ($layout === 'grid')
</div>
@else
</td>
</tr>
@endif
