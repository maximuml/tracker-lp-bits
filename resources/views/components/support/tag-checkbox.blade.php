@props(['value', 'checked', 'text'])
<label><input type="checkbox" name="tags[]" value="{{ $value }}" {{ $checked }} />{{ $text }}</label>
