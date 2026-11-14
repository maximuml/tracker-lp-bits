@props(['layout' => 'tr', 'label', 'name', 'value' => '', 'note' => null, 'size' => null])
<x-settings-row :layout="$layout" :label="$label" :for="'set-'.$name">
    <input type="text" id="set-{{ $name }}" name="{{ $name }}" value="{{ (string) $value }}"@if ($size !== null) size="{{ $size }}"@endif>
    @if ($note !== null && $note !== '') {{ $note }}@endif
</x-settings-row>
