@props(['name', 'yes' => false, 'no' => false, 'disabled' => false, 'yesLabel' => '', 'noLabel' => ''])
<input type="radio" name="{{ $name }}" value="yes"@if ($yes) checked="checked"@endif @if ($disabled) disabled='disabled'@endif />{{ $yesLabel }} <input type="radio" name="{{ $name }}" value="no"@if ($no) checked="checked"@endif @if ($disabled) disabled='disabled'@endif />{{ $noLabel }}
