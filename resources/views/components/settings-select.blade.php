@props(['layout' => 'tr', 'label', 'name', 'options' => [], 'selected' => null, 'note' => null])
<x-settings-row :layout="$layout" :label="$label">
    <select name="{{ $name }}" aria-label="{{ trim(strip_tags($label instanceof \Illuminate\Contracts\Support\Htmlable ? $label->toHtml() : (string) $label)) }}">
        @foreach ($options as $val => $optionLabel)
            <option value="{{ $val }}"@if ((string) $selected === (string) $val) selected @endif>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($note !== null && $note !== '') {{ $note }}@endif
</x-settings-row>
