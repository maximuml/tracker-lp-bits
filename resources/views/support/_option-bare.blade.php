@props(['value', 'selected', 'label'])
@if($selected)<option value="{{ $value }}" selected>{{ $label }}</option>@else<option value="{{ $value }}">{{ $label }}</option>@endif
