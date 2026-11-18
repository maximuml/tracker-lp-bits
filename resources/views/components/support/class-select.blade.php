@props(['name', 'disabled' => false, 'options', 'ariaLabel' => null])
<select name="{{ $name }}"@if($disabled) disabled="disabled"@endif @if($ariaLabel !== null) aria-label="{{ $ariaLabel }}"@endif>@foreach ($options as $option){{ $option }}
@endforeach</select>
