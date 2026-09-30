{{-- Native datetime-local input pair (was Form::datetimepickerInput).
     The jQuery datetimepicker was already replaced by the native input;
     this is just the label + control markup. --}}
@props(['label' => '', 'name' => '', 'value' => ''])<label for="datetime-picker-{{ $name }}">{{ $label }}</label><input type="datetime-local" id="datetime-picker-{{ $name }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off" {{ $attributes }}>
