@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'note' => null])
<x-settings-row :layout="$layout" :label="$label">
    @foreach ($options as $opt)
        <label><input type="checkbox" name="{{ $name }}" value="{{ $opt['value'] }}"@if ($opt['checked'] ?? false) checked @endif @if ($opt['disabled'] ?? false) disabled @endif>{{ $opt['label'] ?? '' }}</label>&nbsp;
    @endforeach
    @if ($note !== null && $note !== '')<br>{{ $note }}@endif
</x-settings-row>
