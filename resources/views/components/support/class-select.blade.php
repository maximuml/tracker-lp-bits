@props(['name', 'disabled' => false, 'options'])
<select name="{{ $name }}"@if($disabled) disabled="disabled"@endif>{{ $options }}</select>
