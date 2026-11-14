@props(['layout' => 'tr', 'label', 'name', 'value' => 'yes', 'note' => null, 'yesLabel' => 'Yes', 'noLabel' => 'No'])
<x-settings-row :layout="$layout" :label="$label">
    <input type="radio" id="{{ $name }}yes" name="{{ $name }}"@if ($value === 'yes') checked @endif value="yes"> <label for="{{ $name }}yes">{{ $yesLabel }}</label>
    <input type="radio" id="{{ $name }}no" name="{{ $name }}"@if ($value === 'no') checked @endif value="no"> <label for="{{ $name }}no">{{ $noLabel }}</label>
    @if ($note !== null && $note !== '')<br>{{ $note }}@endif
</x-settings-row>
