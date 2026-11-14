@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'selected' => null, 'note' => null, 'break' => false])
<x-settings-row :layout="$layout" :label="$label">
    @foreach ($options as $val => $option)
        @if (is_array($option))
            <label><input type="radio" name="{{ $name }}" value="{{ $option['value'] ?? $val }}"@if ((string) $selected === (string) ($option['value'] ?? $val)) checked @endif @if ($option['disabled'] ?? false) disabled @endif>{{ $option['label'] ?? '' }}</label>@if ($break)<br>@else&nbsp;@endif
        @else
            <label><input type="radio" name="{{ $name }}" value="{{ $val }}"@if ((string) $selected === (string) $val) checked @endif>{{ $option }}</label>@if ($break)<br>@else&nbsp;@endif
        @endif
    @endforeach
    @if ($note !== null && $note !== '')<br>{{ $note }}@endif
</x-settings-row>
