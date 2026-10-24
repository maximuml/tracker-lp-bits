@props(['cls' => null, 'label'])
<b>@if ($cls)<span class="{{ $cls }}">{{ $label }}</span>@else{{ $label }}@endif</b>
